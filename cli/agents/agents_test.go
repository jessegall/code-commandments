package agents

import (
	"errors"
	"os"
	"path/filepath"
	"reflect"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

// TestInstructionsAreInjectedAsThePHPToolInjectsThem holds the injection to what the PHP tool's own Instructions left
// for each document, recorded as testdata/injected/<case>.md.
func TestInstructionsAreInjectedAsThePHPToolInjectsThem(t *testing.T) {
	block := "<!-- BEGIN: briefing (auto-generated, run `composer update`) -->\nold\n<!-- END: briefing -->"

	for name, document := range map[string]*string{
		"no-file":              nil,
		"no-block":             ptr("# Mine\n\nMy own words.\n"),
		"stale-block":          ptr("# Mine\n\n" + block + "\n\nAfter.\n"),
		"windows-line-endings": ptr("# Mine\r\n\r\n" + strings.ReplaceAll(block, "\n", "\r\n") + "\r\n"),
		"byte-order-mark":      ptr(bom + "# Mine\n\n" + block + "\n"),
		"no-trailing-newline":  ptr("# Mine"),
		"quoted-block":         ptr("# Mine\n\nWrite `<!-- END: briefing -->` to close it.\n"),
	} {
		t.Run(name, func(t *testing.T) {
			golang := t.TempDir()
			if document != nil {
				must(t, os.WriteFile(filepath.Join(golang, "AGENTS.md"), []byte(*document), 0o644))
			}

			want, _ := os.ReadFile(filepath.Join("testdata", "injected", name+".md"))
			must(t, InstructionsAt(filepath.Join(golang, "AGENTS.md"), golang).Inject("briefing", "\nThe canon.\n\n"))

			if got, _ := os.ReadFile(filepath.Join(golang, "AGENTS.md")); string(got) != string(want) {
				t.Errorf("injected\n%q\nthe PHP tool injected\n%q", got, want)
			}
		})
	}
}

func TestMarkersThatCannotBeTrustedRefuseTheInjection(t *testing.T) {
	begin, end := "<!-- BEGIN: briefing (auto-generated, run `composer update`) -->", "<!-- END: briefing -->"

	for document, reason := range map[string]string{
		begin + "\n" + end + "\n" + begin + "\n" + end + "\n": "the document carries more than one of them",
		begin + "\nno end\n":      "it has a BEGIN marker with no END",
		end + "\n" + begin + "\n": "its END marker stands above its BEGIN",
	} {
		dir := t.TempDir()
		path := filepath.Join(dir, "AGENTS.md")
		must(t, os.WriteFile(path, []byte(document), 0o644))

		var refused *Refused
		if err := InstructionsAt(path, dir).Inject("briefing", "new"); !errors.As(err, &refused) || !strings.Contains(refused.Reason, reason) {
			t.Errorf("%q: %v", document, err)
		}

		if after, _ := os.ReadFile(path); string(after) != document {
			t.Errorf("%q was rewritten", document)
		}
	}
}

func TestAFileOutsideTheProjectIsLeftAlone(t *testing.T) {
	project, elsewhere := t.TempDir(), t.TempDir()
	must(t, os.Symlink(filepath.Join(elsewhere, "CLAUDE.md"), filepath.Join(project, "CLAUDE.md")))
	must(t, os.WriteFile(filepath.Join(elsewhere, "CLAUDE.md"), []byte("theirs\n"), 0o644))

	var refused *Refused
	if err := InstructionsAt(filepath.Join(project, "CLAUDE.md"), project).Inject("x", "y"); !errors.As(err, &refused) {
		t.Errorf("err %v", err)
	}
}

func TestOneFileUnderTwoNamesIsTheSameFile(t *testing.T) {
	dir := t.TempDir()
	must(t, os.WriteFile(filepath.Join(dir, "AGENTS.md"), []byte("x"), 0o644))
	must(t, os.Symlink("AGENTS.md", filepath.Join(dir, "CLAUDE.md")))

	agents, claude := InstructionsAt(filepath.Join(dir, "AGENTS.md"), dir), InstructionsAt(filepath.Join(dir, "CLAUDE.md"), dir)
	fresh := InstructionsAt(filepath.Join(dir, "GEMINI.md"), dir)

	if !agents.SameFileAs(claude) || agents.SameFileAs(fresh) || fresh.SameFileAs(InstructionsAt(filepath.Join(dir, "OTHER.md"), dir)) {
		t.Error("sameness decided by name rather than by inode")
	}
}

func TestASkillIsLinkedRelativelyAndOnlyOnce(t *testing.T) {
	root := t.TempDir()
	target := filepath.Join(root, ".agents", "skills", "commandments-x")
	link := filepath.Join(root, ".claude", "skills", "commandments-x")
	must(t, os.MkdirAll(target, 0o755))
	must(t, os.WriteFile(filepath.Join(target, "SKILL.md"), []byte("skill"), 0o644))

	if !Point(link, target) || !Point(link, target) {
		t.Fatal("not linked")
	}

	if named, err := os.Readlink(link); err != nil || named != "../../.agents/skills/commandments-x" {
		t.Errorf("%q %v", named, err)
	}

	if Point(filepath.Join(root, "nowhere"), filepath.Join(root, "missing")) {
		t.Error("linked a target that does not exist")
	}
}

func TestAProjectTurnsAnAgentOffInItsConfig(t *testing.T) {
	kept := ForProject(config.Config{Disabled: []config.Rule{{Kind: config.Agent, Name: "CodexAgent"}}})

	if !reflect.DeepEqual(kept, []Agent{Claude{}}) {
		t.Errorf("%v", kept)
	}
}

func TestAnAgentTheToolDoesNotShipIsSaidToBeSkipped(t *testing.T) {
	warnings := Unshipped(config.Config{Agents: []string{"ClaudeAgent", "CursorAgent"}})

	if len(warnings) != 1 || !strings.Contains(warnings[0], "CursorAgent, which the tool does not ship") {
		t.Errorf("%q", warnings)
	}
}

func ptr(text string) *string {
	return &text
}

func must(t *testing.T, err error) {
	t.Helper()

	if err != nil {
		t.Fatal(err)
	}
}

// TestClaudesBlockNamesWhatEnforcesTheDisciplines holds the block to the project it is written into: where the agent
// journal runs the plugin, the plugin enforces the disciplines and the package's hooks step aside, so the block says
// so; anywhere else the hooks wired into .claude/settings.json do.
func TestClaudesBlockNamesWhatEnforcesTheDisciplines(t *testing.T) {
	plain, driven := t.TempDir(), t.TempDir()
	manifest := filepath.Join(driven, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}

	if block := (Claude{}).Instructions(plain); !strings.Contains(block, "Hooks are wired into") || strings.Contains(block, "{{enforced}}") {
		t.Errorf("a project with the package's own hooks reads:\n%s", block)
	}
	if block := (Claude{}).Instructions(driven); !strings.Contains(block, "journal's\ncode-commandments plugin judges") || strings.Contains(block, "Hooks are wired into") {
		t.Errorf("a project the journal drives reads:\n%s", block)
	}
}
