package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func journalThatSays(t *testing.T, code int, says string) string {
	t.Helper()

	folder := t.TempDir()
	path := filepath.Join(folder, "journal")

	script := "#!/bin/sh\necho \"$@\" > \"" + filepath.Join(folder, "asked") + "\"\n" +
		"echo '" + says + "'\nexit " + itoa(code) + "\n"

	if err := os.WriteFile(path, []byte(script), 0o755); err != nil {
		t.Fatal(err)
	}

	t.Setenv("JOURNAL_BIN", path)

	return folder
}

func itoa(number int) string {
	if number == 0 {
		return "0"
	}

	digits := ""
	for number > 0 {
		digits = string(rune('0'+number%10)) + digits
		number /= 10
	}

	return digits
}

func TestAMergesFindingsGoToTheWorkerThatWroteThem(t *testing.T) {
	folder := journalThatSays(t, 0, "sent")

	sending, refusal := TellsTheWorker(t.TempDir(), "6", "4 sins across 3 skills")
	if sending != ItHasTheWords {
		t.Fatalf("the worker was not told: %v, %s", sending, refusal)
	}

	asked, err := os.ReadFile(filepath.Join(folder, "asked"))
	if err != nil {
		t.Fatal(err)
	}

	if said := string(asked); !strings.Contains(said, "worker send 6") {
		t.Errorf("the journal was asked %q, which does not send worker 6 its own findings", said)
	}

	if said := string(asked); !strings.Contains(said, "4 sins across 3 skills") {
		t.Errorf("the journal was asked %q, which carries none of the findings", said)
	}
}

func TestAWorkerStillInsideItsMergeIsToldOnceTheMergeEnds(t *testing.T) {
	folder := t.TempDir()
	asked := filepath.Join(folder, "asked")
	path := filepath.Join(folder, "journal")

	script := "#!/bin/sh\nif [ -e \"" + asked + "\" ]; then echo sent; exit 0; fi\ntouch \"" + asked + "\"\n" +
		"echo 'worker 6 has a command running in output 12'\nexit 1\n"

	if err := os.WriteFile(path, []byte(script), 0o755); err != nil {
		t.Fatal(err)
	}

	t.Setenv("JOURNAL_BIN", path)

	sending, refusal := TellsTheWorker(t.TempDir(), "6", "1 sin")
	if sending != ItHasTheWords {
		t.Fatalf("a send made while the merge was still ending gave up: %v, %s", sending, refusal)
	}
}

func TestARetiredWorkersFindingsFallBackToTheMainAgent(t *testing.T) {
	journalThatSays(t, 5, "worker 6 is retired")

	sending, refusal := TellsTheWorker(t.TempDir(), "6", "4 sins across 3 skills")
	if sending != Retired {
		t.Fatalf("a send refused because the worker has no worktree reads as %v, so its findings would not fall "+
			"back to the main agent", sending)
	}

	if !strings.Contains(refusal, "retired") {
		t.Errorf("the refusal is not carried to the agent: %q", refusal)
	}
}

func TestAnyOtherRefusalIsToldToTheAgentWithItsReason(t *testing.T) {
	journalThatSays(t, 3, "the environment is locked")

	sending, refusal := TellsTheWorker(t.TempDir(), "6", "4 sins across 3 skills")
	if sending != Failed {
		t.Fatalf("a refusal for another reason reads as %v, so the agent would be told the worker is gone when it "+
			"is not", sending)
	}

	if !strings.Contains(refusal, "locked") {
		t.Errorf("the reason is lost: %q", refusal)
	}
}

func TestNoWorkerNamedMeansTheAgentIsTold(t *testing.T) {
	journalThatSays(t, 0, "sent")

	if sending, _ := TellsTheWorker(t.TempDir(), "", "4 sins"); sending != Retired {
		t.Errorf("a merge naming no worker reads as %v rather than something for the agent", sending)
	}
}

func TestWithoutTheJournalsCommandTheAgentIsToldAndNothingIsLost(t *testing.T) {
	t.Setenv("JOURNAL_BIN", filepath.Join(t.TempDir(), "not-here"))
	t.Setenv("PATH", t.TempDir())

	sending, refusal := TellsTheWorker(t.TempDir(), "6", "4 sins")
	if sending != Failed {
		t.Fatalf("with no journal command to call, the outcome reads as %v, so the findings would be dropped", sending)
	}

	if refusal == "" {
		t.Error("nothing says why the worker could not be told")
	}
}
