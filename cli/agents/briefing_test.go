package agents

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

func TestBriefWritesTheBriefingFileAndOneLineInEachAgentsOwnFile(t *testing.T) {
	root := t.TempDir()

	if refused := Brief(root, config.Config{}); len(refused) > 0 {
		t.Fatalf("a fresh project refused the briefing: %v", refused)
	}

	briefing := read(t, filepath.Join(root, BriefingFile))
	if !strings.HasPrefix(briefing, writtenBySync) || !strings.Contains(briefing, "Working here as Claude Code") {
		t.Errorf("%s does not open on the line that says who writes it, or lacks Claude's own part:\n%s", BriefingFile, briefing)
	}

	canon := read(t, filepath.Join(root, "AGENTS.md"))
	if canon != "# AGENTS\n\n"+readsTheBriefing+"\n" {
		t.Errorf("AGENTS.md holds more or less than the one line that points at the briefing:\n%s", canon)
	}

	claude := read(t, filepath.Join(root, "CLAUDE.md"))
	if claude != "# CLAUDE\n\n@"+BriefingFile+"\n" {
		t.Errorf("CLAUDE.md holds more or less than the one line that imports the briefing:\n%s", claude)
	}

	if refused := Brief(root, config.Config{}); len(refused) > 0 {
		t.Fatalf("a second sync refused the briefing: %v", refused)
	}
	if read(t, filepath.Join(root, "AGENTS.md")) != canon || read(t, filepath.Join(root, "CLAUDE.md")) != claude || read(t, filepath.Join(root, BriefingFile)) != briefing {
		t.Error("a second sync with nothing new changed a file")
	}

	off := t.TempDir()
	Brief(off, config.Config{Disabled: []config.Rule{{Kind: config.Agent, Name: "ClaudeAgent"}}})

	if _, err := os.Stat(filepath.Join(off, "CLAUDE.md")); err == nil {
		t.Error("an agent the project turned off was still written a file of its own")
	}
	if strings.Contains(read(t, filepath.Join(off, BriefingFile)), "Working here as Claude Code") {
		t.Error("the briefing carries the part of an agent the project turned off")
	}
}

func TestAProjectHoldingTheFormerBlocksIsLeftWithTheOneLine(t *testing.T) {
	root := t.TempDir()
	block := func(name string) string {
		return "<!-- BEGIN: " + name + " (auto-generated, run `composer update`) -->\nthe whole briefing, as it was injected\n<!-- END: " + name + " -->\n"
	}
	if err := os.WriteFile(filepath.Join(root, "AGENTS.md"), []byte("# Ours\n\nKeep this.\n\n"+block(BriefingBlock)), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "CLAUDE.md"), []byte("# Ours\n\n"+block(ClaudeBlock)+"\nKeep this too.\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if refused := Brief(root, config.Config{}); len(refused) > 0 {
		t.Fatalf("a project with the former blocks refused the briefing: %v", refused)
	}

	for file, line := range map[string]string{"AGENTS.md": readsTheBriefing, "CLAUDE.md": "@" + BriefingFile} {
		now := read(t, filepath.Join(root, file))
		if strings.Contains(now, "BEGIN:") || strings.Contains(now, "as it was injected") {
			t.Errorf("%s still holds the former block:\n%s", file, now)
		}
		if strings.Count(now, line) != 1 || !strings.Contains(now, "Keep this") {
			t.Errorf("%s does not hold the one line once beside what the project wrote:\n%s", file, now)
		}
	}
}

func TestABriefingFileOfTheProjectsOwnIsNotWrittenOver(t *testing.T) {
	root := t.TempDir()
	if err := os.WriteFile(filepath.Join(root, BriefingFile), []byte("# Our own commandments\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if refused := Brief(root, config.Config{}); len(refused) != 1 {
		t.Fatalf("a %s the project wrote itself gave %d refusals, want 1: %v", BriefingFile, len(refused), refused)
	}
	if got := read(t, filepath.Join(root, BriefingFile)); got != "# Our own commandments\n" {
		t.Errorf("the project's own file was written over:\n%s", got)
	}
}

func TestAnAgentThatReadsTheCanonIsNotWrittenToTwice(t *testing.T) {
	root := t.TempDir()

	if err := os.WriteFile(filepath.Join(root, "AGENTS.md"), []byte("# AGENTS\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Link(filepath.Join(root, "AGENTS.md"), filepath.Join(root, "CLAUDE.md")); err != nil {
		t.Skipf("this filesystem has no hard links: %v", err)
	}

	Brief(root, config.Config{})

	if got := strings.Count(read(t, filepath.Join(root, "AGENTS.md")), "@"+BriefingFile); got != 0 {
		t.Errorf("the one file carries Claude's own line %d times; it already points at the briefing", got)
	}
}

func TestAFileThatCannotBeTrustedIsRefusedAndTheRestAreWritten(t *testing.T) {
	root := t.TempDir()
	ambiguous := "<!-- BEGIN: " + BriefingBlock + " -->\n<!-- BEGIN: " + BriefingBlock + " -->\n"

	if err := os.WriteFile(filepath.Join(root, "AGENTS.md"), []byte(ambiguous), 0o644); err != nil {
		t.Fatal(err)
	}

	refused := Brief(root, config.Config{})

	if len(refused) != 1 {
		t.Fatalf("the ambiguous AGENTS.md gave %d refusals, want exactly 1: %v", len(refused), refused)
	}
	if got := read(t, filepath.Join(root, "AGENTS.md")); got != ambiguous {
		t.Errorf("the refused file was written anyway:\n%s", got)
	}
	if !strings.Contains(read(t, filepath.Join(root, "CLAUDE.md")), "@"+BriefingFile) {
		t.Error("the refusal on AGENTS.md stopped CLAUDE.md being written")
	}
}

func read(t *testing.T, path string) string {
	t.Helper()

	contents, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("reading %s: %v", path, err)
	}

	return string(contents)
}
