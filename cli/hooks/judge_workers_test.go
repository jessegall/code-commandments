package hooks

import "testing"

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
