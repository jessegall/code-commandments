package judge

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/warm"
)

func noJournalVariables(t *testing.T) {
	t.Helper()

	for _, variable := range os.Environ() {
		named, value, _ := strings.Cut(variable, "=")
		if !strings.HasPrefix(named, journalVariables) {
			continue
		}

		os.Unsetenv(named)
		t.Cleanup(func() { os.Setenv(named, value) })
	}
}

func refusedIn(t *testing.T, project, signal string) {
	t.Helper()

	code, said, warned := judgedIn(t, project, ".", "--no-checklist")

	if code != coldReadRefused {
		t.Fatalf("with only %s to say a journal is there, the run exits %d, want %d: %s", signal, code, coldReadRefused, warned)
	}
	if !strings.Contains(warned, "--allow-cold-read") || !strings.Contains(warned, "journal plugin services") {
		t.Errorf("it says %q, which names neither how to start the service nor how to read anyway", warned)
	}
	if said != "" {
		t.Errorf("it judged although it refused: %q", said)
	}
}

func TestAJournalVariableAloneRefusesAColdRead(t *testing.T) {
	noJournalVariables(t)
	t.Setenv("JOURNAL_PROJECT_ROOT", "/somewhere")

	refusedIn(t, emptyProject(t), "a JOURNAL_ variable")
}

func TestAJournalFolderAboveTheRunAloneRefusesAColdRead(t *testing.T) {
	noJournalVariables(t)

	project := emptyProject(t)
	worktree := filepath.Join(project, ".journal", "worktrees", "development", "worker-1")
	if err := os.MkdirAll(worktree, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(worktree, "cart.php"), []byte("<?php\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	refusedIn(t, worktree, "a .journal folder above the run")
}

func TestThePluginAtTheProjectRootAloneRefusesAColdRead(t *testing.T) {
	noJournalVariables(t)

	project := emptyProject(t)
	manifest := filepath.Join(project, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, command := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "cart.php"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", project}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	beside, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	worktree := filepath.Join(beside, "worker-1")
	if out, err := exec.Command("git", "-C", project, "worktree", "add", "-q", "-b", "a-worker", worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	refusedIn(t, worktree, "the plugin at the root git names")
}

func TestAProjectAServiceOnceServedAloneRefusesAColdRead(t *testing.T) {
	noJournalVariables(t)
	t.Setenv("XDG_CACHE_HOME", t.TempDir())

	project := emptyProject(t)
	stop, err := warm.Register(project)
	if err != nil {
		t.Fatal(err)
	}
	stop()

	refusedIn(t, project, "a service that served it once")
}

func TestARunAllowedToReadColdReadsTheTreeEvenWhereAJournalIs(t *testing.T) {
	noJournalVariables(t)
	t.Setenv("JOURNAL_PROJECT_ROOT", "/somewhere")

	code, _, warned := judgedIn(t, emptyProject(t), ".", "--no-checklist", "--allow-cold-read")

	if code == coldReadRefused {
		t.Errorf("a run given --allow-cold-read refused: %s", warned)
	}
	if !strings.Contains(warned, "reads the whole tree itself") {
		t.Errorf("it says %q, which does not say it read the whole tree", warned)
	}
}

func TestARunWithNoSignOfAJournalReadsTheTreeAsBefore(t *testing.T) {
	noJournalVariables(t)

	code, _, warned := judgedIn(t, emptyProject(t), ".", "--no-checklist")

	if code == coldReadRefused {
		t.Errorf("a project with no journal, as in CI, refused to judge: %s", warned)
	}
}
