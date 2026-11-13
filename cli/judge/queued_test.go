package judge

import (
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func journalAnswering(t *testing.T, script string) string {
	t.Helper()

	asked := filepath.Join(t.TempDir(), "asked")
	journal := filepath.Join(t.TempDir(), "journal")
	if err := os.WriteFile(journal, []byte("#!/bin/sh\necho \"$@\" > "+asked+"\n"+script+"\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("JOURNAL_BIN", journal)

	return asked
}

func TestAJudgeInAJournalsProjectRunsInItsQueueAndStreamsTheRunBack(t *testing.T) {
	root := t.TempDir()
	asked := journalAnswering(t, "echo '1 sin in src/order.py'\nexit 3")

	var out strings.Builder
	code, taken := throughTheQueue(workspace.At(root, ""), root, cli.InputOf("judge", "--changes"), cli.Console{Out: &out, Err: io.Discard})

	if !taken || code != 3 {
		t.Fatalf("the queued judge was taken %v with exit code %d, want taken with the run's own 3", taken, code)
	}
	if !strings.Contains(out.String(), "1 sin in src/order.py") {
		t.Errorf("the queued run's output did not reach the terminal: %q", out.String())
	}

	self, _ := os.Executable()
	line, _ := os.ReadFile(asked)
	if want := "plugin queue code-commandments --kind judge --checkout " + root + " -- env " + queuedMark + "=1 " + self + " judge --changes"; strings.TrimSpace(string(line)) != want {
		t.Errorf("the journal was asked %q, want %q", strings.TrimSpace(string(line)), want)
	}
}

func TestAJudgeTheJournalRefusesRunsHere(t *testing.T) {
	root := t.TempDir()
	journalAnswering(t, "echo 'refused: this environment has no running main agent' >&2\nexit 1")

	if _, taken := throughTheQueue(workspace.At(root, ""), root, cli.InputOf("judge"), cli.Console{Out: io.Discard, Err: io.Discard}); taken {
		t.Error("a judge the journal refused counts as run, so nothing judges at all")
	}
}

func TestAJudgeTheJournalStartedIsNeverQueuedAgain(t *testing.T) {
	for _, name := range startedByTheJournal {
		t.Setenv(name, "")
	}
	if insideTheJournal() {
		t.Fatal("a judge typed in a terminal counts as started by the journal")
	}

	for _, name := range startedByTheJournal {
		t.Setenv(name, "1")
		if !insideTheJournal() {
			t.Errorf("a judge started with %s set would queue itself again", name)
		}
		t.Setenv(name, "")
	}
}
