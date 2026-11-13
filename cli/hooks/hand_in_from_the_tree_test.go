package hooks

import (
	"encoding/json"
	"strings"
	"testing"
)

// A hand-in is judged whole, every rule asked, with no tree held: the checkout is read through the one cache.
func TestAHandInIsJudgedWholeWithNoTreeHeld(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := DeliveryHeld(deliveringOf(root, "src/refund.py"))

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hand-in was answered %q: %v", held, err)
	}

	if answer["refuse"] == nil {
		t.Errorf("a hand-in carrying a sin was answered %v; the work goes back for it", answer)
	}

	if note := asText(answer["whisper"]); strings.Contains(note, "not judged in full") {
		t.Errorf("a hand-in judged whole says it was judged in part: %q", note)
	}
}

func TestAHandInIsHeldWhenItsOwnLineSwitchesTheSettingOn(t *testing.T) {
	root, _, _ := projectWithASin(t)

	t.Setenv(journalSettings, `{"judge_hand_ins": "false"}`)

	line := deliveringOf(root, "src/refund.py")
	line["settings"] = map[string]any{"judge_workers": true, "judge_hand_ins": true}

	if held := DeliveryHeld(line); held == "" {
		t.Error("with hand-ins switched on on the line, the hand-in passed unjudged")
	}
}
