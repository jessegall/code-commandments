package hooks

import (
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

func afterHandInLine(root string, settings map[string]any) map[string]any {
	line := deliveringOf(root, "src/refund.py")
	line["environment"] = "main"
	line["agent"] = float64(7)
	line["settings"] = settings

	return line
}

func TestAHandInJudgedAfterGoesThroughUnheldAndTheWorkerIsToldItsSinAfterwards(t *testing.T) {
	root, _, data := projectWithASin(t)

	line := afterHandInLine(root, map[string]any{"judge_workers": true, "judge_hand_ins": false, "judge_hand_ins_after": true})

	if held := DeliveryHeld(line); held != "" {
		t.Fatalf("the hand-in was held though judging after it was chosen: %q", held)
	}

	raw, _ := json.Marshal(line)
	kept := []string{filepath.Join(data, "events", "event-1.json")}
	os.MkdirAll(filepath.Dir(kept[0]), 0o755)
	if err := os.WriteFile(kept[0], raw, 0o644); err != nil {
		t.Fatal(err)
	}

	judgeTheEventIn(kept[0], cli.Console{Out: io.Discard, Err: io.Discard})
	queued := drained(t, data)

	if len(queued) != 1 {
		t.Fatalf("the worker was told %d times after its hand-in, want once: %v", len(queued), queued)
	}

	told := asText(queued[0]["whisper"])
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
