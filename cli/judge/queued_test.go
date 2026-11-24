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

	return journalWithQueue(t, "exit 0", script)
}

func journalWithQueue(t *testing.T, help, script string) string {
	t.Helper()

	asked := filepath.Join(t.TempDir(), "asked")
	journal := filepath.Join(t.TempDir(), "journal")
	if err := os.WriteFile(journal, []byte("#!/bin/sh\nif [ \"$3\" = --help ]; then "+help+"; fi\necho \"$@\" > "+asked+"\n"+script+"\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("JOURNAL_BIN", journal)

	return asked
}

func quietProgress() *cli.Progress {
	return cli.NewProgress(io.Discard)
}

func TestAJudgeAnAgentStartsWaitsInTheJournalsQueueAndStreamsTheRunBack(t *testing.T) {
	root := t.TempDir()
	asked := journalAnswering(t, "echo '1 sin in src/order.py'\nexit 3")

	var out strings.Builder
	code, taken := throughTheQueue(workspace.At(root, ""), root, cli.InputOf("judge", "--changes"), cli.Console{Out: &out, Err: io.Discard}, quietProgress())

	if !taken || code != 3 {
		t.Fatalf("the queued judge was taken %v with exit code %d, want taken with the run's own 3", taken, code)
	}
	if !strings.Contains(out.String(), "1 sin in src/order.py") {
		t.Errorf("the queued run's output did not come back: %q", out.String())
	}

	self, _ := os.Executable()
	line, _ := os.ReadFile(asked)
	if want := "plugin queue code-commandments --kind judge --checkout " + root + " -- env " + queuedMark + "=1 " + cli.PlainProgress + "=1 " + self + " judge --changes"; strings.TrimSpace(string(line)) != want {
		t.Errorf("the journal was asked %q, want %q", strings.TrimSpace(string(line)), want)
	}
}

func TestAJudgeTheJournalRefusesRunsHere(t *testing.T) {
	root := t.TempDir()
	journalAnswering(t, "echo 'refused: this environment has no running main agent' >&2\nexit 1")

	if _, taken := throughTheQueue(workspace.At(root, ""), root, cli.InputOf("judge"), cli.Console{Out: io.Discard, Err: io.Discard}, quietProgress()); taken {
		t.Error("a judge the journal refused counts as run, so nothing judges at all")
	}
}

func TestAJudgeAnOlderJournalWithoutAQueueRunsHere(t *testing.T) {
	root := t.TempDir()
	refused := "echo \"journal plugin: error: argument verb: invalid choice: 'queue'\"; exit 2"
	asked := journalWithQueue(t, refused, refused)

	var said strings.Builder
	if _, taken := throughTheQueue(workspace.At(root, ""), root, cli.InputOf("judge"), cli.Console{Out: io.Discard, Err: &said}, quietProgress()); taken {
		t.Error("a journal with no queue verb took the judge, so nothing judges at all")
	}
	if strings.Contains(said.String(), "invalid choice") {
		t.Errorf("the older journal's usage error was shown, which reads as a failed judge: %q", said.String())
	}
	if _, err := os.Stat(asked); err == nil {
		t.Error("a journal with no queue verb was still asked to queue the judge")
	}
}

func TestOnlyAJudgeTheJournalStartedForAnAgentWaitsForTheQueue(t *testing.T) {
	t.Setenv(queuedMark, "")
	for _, name := range startedForAnAgent {
		t.Setenv(name, "")
	}
	if waitsForTheQueue() {
		t.Error("a judge typed in a terminal waits for the journal's queue instead of running right there")
	}

	for _, name := range startedForAnAgent {
		t.Setenv(name, "1")
		if !waitsForTheQueue() {
			t.Errorf("a judge started with %s set skips the queue, so it judges beside another agent's run", name)
		}

		t.Setenv(queuedMark, "1")
		if waitsForTheQueue() {
			t.Errorf("a judge the queue already started (with %s set) queues itself again", name)
		}
		t.Setenv(queuedMark, "")
		t.Setenv(name, "")
	}
}
