package hooks

import (
	"testing"
	"time"
)

func TestWithJudgeWorkersOffNothingOfAWorkerIsJudged(t *testing.T) {
	everythingElseOn := map[string]any{"judge_workers": false, "judge_worker_edits": true, "judge_hand_ins": true, "judge_merges": true, "judge_before_merge": true}
	line := map[string]any{"settings": everythingElseOn}

	if handInsJudged(line) {
		t.Error("a hand-in is judged with judge_workers off")
	}

	if mergesJudged(line) {
		t.Error("a merge is judged with judge_workers off")
	}

	if mergesJudgedBeforeTheyLand(line) {
		t.Error("a merge is judged before it lands with judge_workers off")
	}

	if workerEditsJudgedAt(Moment{Settings: everythingElseOn}) {
		t.Error("a worker's live edit is judged with judge_workers off")
	}
}

func TestWithJudgeWorkersOnEachChoiceStandsAlone(t *testing.T) {
	onlyMerges := map[string]any{"settings": map[string]any{"judge_workers": true, "judge_worker_edits": false, "judge_hand_ins": false, "judge_merges": true, "judge_before_merge": false}}

	if !mergesJudged(onlyMerges) {
		t.Error("merges are chosen and are not judged")
	}

	if handInsJudged(onlyMerges) {
		t.Error("a hand-in is judged though it was not chosen")
	}

	if mergesJudgedBeforeTheyLand(onlyMerges) {
		t.Error("a merge is judged before it lands though that was not chosen")
	}

	if workerEditsJudgedAt(Moment{Settings: onlyMerges["settings"].(map[string]any)}) {
		t.Error("a live edit is judged though it was not chosen")
	}

	onlyEdits := map[string]any{"judge_workers": true, "judge_worker_edits": true}

	if !workerEditsJudgedAt(Moment{Settings: onlyEdits}) {
		t.Error("live edits are chosen and are not judged")
	}
}

func TestAWorkerGetsNoJudgingWhenItsEditsAreNotChosen(t *testing.T) {
	moment := Moment{Role: "worker", Settings: map[string]any{"judge_workers": true, "judge_worker_edits": false}}

	if notForThisRole(moment) == "" {
		t.Error("a worker's edit went on to be judged with live edits off")
	}
}

func TestAnAgentsLiveEditsAreGatheredForFiveSecondsByDefault(t *testing.T) {
	if got := gatheredFor(map[string]any{"settings": map[string]any{}}); got != 5*time.Second {
		t.Errorf("edits are gathered for %v", got)
	}
}

func TestTheWindowForLiveEditsIsASetting(t *testing.T) {
	chosen := map[string]any{"settings": map[string]any{"judge_edit_window": float64(2)}}

	if got := gatheredFor(chosen); got != 2*time.Second {
		t.Errorf("a window of 2 seconds gathers for %v", got)
	}

	alone := map[string]any{"settings": map[string]any{"judge_edit_window": float64(0)}}

	if got := gatheredFor(alone); got != 0 {
		t.Errorf("a window of 0 gathers for %v", got)
	}
}

func TestAWorkerWithItsJudgingOffHearsNothingAtAll(t *testing.T) {
	root := t.TempDir()
	given := map[string]any{"event": "PostToolUse", "cwd": root, "role": "worker", "settings": map[string]any{"judge_workers": false}}

	answer := answerWith(given, every, true)

	if answer.Whisper != nil || answer.Refuse != nil {
		t.Errorf("a worker with judging off was told something: %+v", answer)
	}
}
