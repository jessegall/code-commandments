package agents

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

// TestBriefWritesTheCanonAndEachAgentsOwnFile holds what a project is told to the files that are really read: the
// briefing goes into AGENTS.md, which every agent reads, and an agent with a file of its own gets its block there
// too. An agent the project turned off is told nothing, and an agent that reads AGENTS.md itself is not written to
// twice — the second write would nest its block inside the briefing's.
func TestBriefWritesTheCanonAndEachAgentsOwnFile(t *testing.T) {
	root := t.TempDir()

	if refused := Brief(root, config.Config{}); len(refused) > 0 {
		t.Fatalf("a fresh project refused the briefing: %v", refused)
	}

	canon := read(t, filepath.Join(root, "AGENTS.md"))
	if !strings.Contains(canon, BriefingBlock) {
		t.Errorf("AGENTS.md holds no %q block:\n%s", BriefingBlock, canon)
	}

	claude := read(t, filepath.Join(root, "CLAUDE.md"))
	if !strings.Contains(claude, ClaudeBlock) {
		t.Errorf("CLAUDE.md holds no %q block:\n%s", ClaudeBlock, claude)
	}
	if strings.Contains(claude, BriefingBlock) {
		t.Errorf("CLAUDE.md carries the briefing itself; it imports AGENTS.md:\n%s", claude)
	}

	off := t.TempDir()
	Brief(off, config.Config{Disabled: []config.Rule{{Kind: config.Agent, Name: "ClaudeAgent"}}})

	if _, err := os.Stat(filepath.Join(off, "CLAUDE.md")); err == nil {
		t.Error("an agent the project turned off was still written a file of its own")
	}
}

// TestAnAgentThatReadsTheCanonIsNotWrittenToTwice holds the SameFileAs guard: where CLAUDE.md IS AGENTS.md, the
// agent's own block must not be injected into the file that already carries the briefing.
func TestAnAgentThatReadsTheCanonIsNotWrittenToTwice(t *testing.T) {
	root := t.TempDir()

	if err := os.WriteFile(filepath.Join(root, "AGENTS.md"), []byte("# AGENTS\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Link(filepath.Join(root, "AGENTS.md"), filepath.Join(root, "CLAUDE.md")); err != nil {
		t.Skipf("this filesystem has no hard links: %v", err)
	}

	Brief(root, config.Config{})

	if got := strings.Count(read(t, filepath.Join(root, "AGENTS.md")), ClaudeBlock); got != 0 {
		t.Errorf("the one file carries Claude's own block %d times; it already carries the briefing", got)
	}
}

// TestAFileThatCannotBeTrustedIsRefusedAndTheRestAreWritten holds a refusal to the file it names: a briefing that
// cannot be written into one file still reaches every other, since an upgrade refreshing them must not stop at the
// first project file a reader left ambiguous.
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
	if !strings.Contains(read(t, filepath.Join(root, "CLAUDE.md")), ClaudeBlock) {
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
