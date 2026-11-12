package hooks

import (
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
)

func afterHandInLine(root string, settings map[string]any) map[string]any {
	line := deliveringOf(root, generousWindow, "src/refund.py")
	line["environment"] = "main"
	line["agent"] = float64(7)
	line["settings"] = settings

	return line
}

func TestAHandInJudgedAfterGoesThroughUnheldAndTheWorkerIsToldItsSinAfterwards(t *testing.T) {
	root, _, data := projectWithASin(t)

	line := afterHandInLine(root, map[string]any{"judge_workers": true, "judge_hand_ins": false, "judge_hand_ins_after": true})

	if !handInsJudgedAfter(line) {
		t.Fatal("judging after a hand-in is chosen and the hand-in is not judged after")
	}

	journal := filepath.Join(t.TempDir(), "journal")
	if err := os.WriteFile(journal, []byte("#!/bin/sh\nexit 0\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("JOURNAL_BIN", journal)

	if held := DeliveryHeld(line); held != "" {
		t.Fatalf("the hand-in was held though judging after it was chosen: %q", held)
	}

	var kept []string
	for until := time.Now().Add(10 * time.Second); len(kept) == 0 && time.Now().Before(until); time.Sleep(50 * time.Millisecond) {
		kept, _ = filepath.Glob(filepath.Join(data, eventsFolder, "event-*.json"))
	}
	if len(kept) != 1 {
		t.Fatalf("the hand-in was not handed to the journal's queue to judge: %v", kept)
	}

	judgeTheEventIn(kept[0], cli.Console{Out: io.Discard, Err: io.Discard})
	queued := drained(t, data)

	if len(queued) != 1 {
		t.Fatalf("the worker was told %d times after its hand-in, want once: %v", len(queued), queued)
	}

	told := asText(queued[0]["tell"])
	for _, want := range []string{"1 sin", "src/refund.py", "judged after the hand-in went through"} {
		if !strings.Contains(told, want) {
			t.Errorf("the worker was told %q, which lacks %q", told, want)
		}
	}

	if agent := asAgent(queued[0]["agent"]); agent != 7 {
		t.Errorf("the sin was queued for agent %d, not the worker who handed in", agent)
	}
}

func TestAHandInJudgedAfterTellsNothingWhenItIsClean(t *testing.T) {
	root, _, data := projectWithASin(t)

	line := afterHandInLine(root, map[string]any{"judge_workers": true, "judge_hand_ins": false, "judge_hand_ins_after": true})
	line["data"].(map[string]any)["files"] = []any{"src/missing.py"}

	mergeOf(line).judgesAfterwards()

	if queued := drained(t, data); len(queued) > 0 {
		t.Errorf("a hand-in with no sin told its worker anyway: %v", queued)
	}
}

func TestJudgingAfterAHandInNeedsTheMasterOnAndJudgingBeforeOff(t *testing.T) {
	root, _, _ := projectWithASin(t)

	for name, settings := range map[string]map[string]any{
		"the choice is off":            {"judge_workers": true, "judge_hand_ins": false, "judge_hand_ins_after": false},
		"the choice is not named":      {"judge_workers": true, "judge_hand_ins": false},
		"the master is off":            {"judge_workers": false, "judge_hand_ins": false, "judge_hand_ins_after": true},
		"the hand-in is judged before": {"judge_workers": true, "judge_hand_ins": true, "judge_hand_ins_after": true},
	} {
		if handInsJudgedAfter(afterHandInLine(root, settings)) {
			t.Errorf("a hand-in is judged after it went through when %s", name)
		}
	}
}
