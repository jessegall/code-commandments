package hooks

import (
	"testing"
)

func TestAWorkerGetsNoJudgingWhenItsEditsAreNotChosen(t *testing.T) {
	moment := Moment{Role: "worker", Settings: map[string]any{"judge_workers": true, "judge_worker_edits": false}}

	if notForThisRole(moment) == "" {
		t.Error("a worker's edit went on to be judged with live edits off")
	}
}

func TestAWorkerWithItsJudgingOffHearsNothingAtAll(t *testing.T) {
	root := t.TempDir()
	given := map[string]any{"event": "PostToolUse", "cwd": root, "role": "worker", "settings": map[string]any{"judge_workers": false}}

	answer := answerWith(given)

	if answer.Whisper != nil || answer.Refuse != nil {
		t.Errorf("a worker with judging off was told something: %+v", answer)
	}
}

func TestNothingIsJudgedOnItsOwnUntilASettingTurnsItOn(t *testing.T) {
	t.Setenv(journalSettings, "")

	if liveEditsJudgedAt(Moment{Settings: map[string]any{}}) {
		t.Error("with nothing chosen, the main agent's edit was judged")
	}

	if workerEditsJudgedAt(Moment{Role: "worker", Settings: map[string]any{}}) {
		t.Error("with nothing chosen, a worker's edit was judged")
	}

	if helpersRunTheCLI(Moment{Role: "worker", Settings: map[string]any{}}) {
		t.Error("with nothing chosen, a worker may run the judge")
	}
}

func TestLiveEditsAreJudgedOnlyWhenTheSettingSwitchesThemOn(t *testing.T) {
	t.Setenv(journalSettings, "")

	if liveEditsJudgedAt(Moment{Settings: map[string]any{"judge_edits": false}}) {
		t.Error("the main agent's edit went on to be judged with live edits off")
	}

	if !liveEditsJudgedAt(Moment{Settings: map[string]any{"judge_edits": "true"}}) {
		t.Error("an edit was left unjudged with live edits on")
	}
}
