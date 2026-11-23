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

func TestTheOneLineIsHeldInAFileHoweverTheProjectWroteIt(t *testing.T) {
	block := "<!-- BEGIN: briefing (auto-generated, run `composer update`) -->\nold\n<!-- END: briefing -->"
	line := "Read the briefing."

	for name, held := range map[string]struct {
		document *string
		want     string
	}{
		"no-file":              {nil, "# AGENTS\n\n" + line + "\n"},
		"no-block":             {ptr("# Mine\n\nMy own words.\n"), "# Mine\n\nMy own words.\n\n" + line + "\n"},
		"stale-block":          {ptr("# Mine\n\n" + block + "\n\nAfter.\n"), "# Mine\n\nAfter.\n\n" + line + "\n"},
		"windows-line-endings": {ptr("# Mine\r\n\r\n" + strings.ReplaceAll(block, "\n", "\r\n") + "\r\n"), "# Mine\r\n\r\n" + line + "\r\n"},
		"byte-order-mark":      {ptr(bom + "# Mine\n\n" + block + "\n"), bom + "# Mine\n\n" + line + "\n"},
		"no-trailing-newline":  {ptr("# Mine"), "# Mine\n\n" + line + "\n"},
		"quoted-block":         {ptr("# Mine\n\nWrite `<!-- END: briefing -->` to close it.\n"), "# Mine\n\nWrite `<!-- END: briefing -->` to close it.\n\n" + line + "\n"},
		"the-line-already":     {ptr("# Mine\n\n" + line + "\n\nAfter.\n"), "# Mine\n\n" + line + "\n\nAfter.\n"},
	} {
		t.Run(name, func(t *testing.T) {
			project := t.TempDir()
			if held.document != nil {
				must(t, os.WriteFile(filepath.Join(project, "AGENTS.md"), []byte(*held.document), 0o644))
			}

			must(t, InstructionsAt(filepath.Join(project, "AGENTS.md"), project).Hold(line, "briefing"))

			if got, _ := os.ReadFile(filepath.Join(project, "AGENTS.md")); string(got) != held.want {
				t.Errorf("the file reads\n%q\nwant\n%q", got, held.want)
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
		if err := InstructionsAt(path, dir).Hold("new", "briefing"); !errors.As(err, &refused) || !strings.Contains(refused.Reason, reason) {
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
	if err := InstructionsAt(filepath.Join(project, "CLAUDE.md"), project).Hold("x", "y"); !errors.As(err, &refused) {
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

func TestTheBriefingIsWrittenByTheJournalOnlyOnceTheJournalWritesIt(t *testing.T) {
	block := "<!-- BEGIN: code-commandments briefing (auto-generated, run `composer update`) -->\nold\n<!-- END: code-commandments briefing -->"
	claude := "<!-- BEGIN: code-commandments skills (auto-generated, run `composer update`) -->\n@AGENTS.md\n<!-- END: code-commandments skills -->"

	for name, project := range map[string]struct {
		plugin, journalFile, withdrawn bool
	}{
		"the journal writes it":               {plugin: true, journalFile: true, withdrawn: true},
		"a journal too old to write it":       {plugin: true},
		"the journal runs without the plugin": {journalFile: true},
		"no journal":                          {},
	} {
		t.Run(name, func(t *testing.T) {
			root := t.TempDir()
			must(t, os.WriteFile(filepath.Join(root, "AGENTS.md"), []byte("# Mine\n\n"+block+"\n\nAfter.\n"), 0o644))
			must(t, os.WriteFile(filepath.Join(root, "CLAUDE.md"), []byte(claude+"\n\nMy own words.\n"), 0o644))

			if project.plugin {
				manifest := filepath.Join(root, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
				must(t, os.MkdirAll(filepath.Dir(manifest), 0o755))
				must(t, os.WriteFile(manifest, []byte("{}"), 0o644))
			}

			if project.journalFile {
				must(t, os.WriteFile(filepath.Join(root, "JOURNAL.md"), []byte("the journal's text\n"), 0o644))
			}

			if refused := Brief(root, config.Config{}); len(refused) > 0 {
				t.Fatalf("the briefing was refused: %v", refused)
			}

			agents, _ := os.ReadFile(filepath.Join(root, "AGENTS.md"))
			instructions, _ := os.ReadFile(filepath.Join(root, "CLAUDE.md"))

			briefing, briefed := os.ReadFile(filepath.Join(root, BriefingFile))
			if !project.withdrawn {
				if !strings.Contains(string(briefing), "Finding and fixing sins") || !strings.Contains(string(agents), readsTheBriefing) {
					t.Errorf("the project's own briefing was not written, or AGENTS.md does not point at it:\n%s\n%s", briefing, agents)
				}

				return
			}

			if briefed == nil {
				t.Errorf("%s is still there once the journal writes the briefing", BriefingFile)
			}

			if string(agents) != "# Mine\n\nAfter.\n" {
				t.Errorf("AGENTS.md should keep only the owner's text once the journal writes the briefing:\n%q", agents)
			}

			if string(instructions) != "My own words.\n" {
				t.Errorf("CLAUDE.md should keep only the owner's text once the journal writes the briefing:\n%q", instructions)
			}
		})
	}
}

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
