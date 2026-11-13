package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

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

func TestOnVersion4TheWindowForLiveEditsIsReadFromTheSettingsFile(t *testing.T) {
	file := filepath.Join(t.TempDir(), "settings.json")
	if err := os.WriteFile(file, []byte(`{"judge_edit_window": 2, "judge_workers": false, "judge_worker_edits": true}`), 0o644); err != nil {
		t.Fatal(err)
	}
	t.Setenv(journalSettings, file)

	if got := gatheredFor(map[string]any{"event": "file_edited"}); got != 2*time.Second {
		t.Errorf("a version 4 line, which carries no settings, gathers edits for %v instead of the 2 seconds chosen", got)
	}

	if workerEditsJudgedAt(Moment{Role: "worker"}) {
		t.Error("with workers switched off in the settings file, a version 4 worker's edit is still judged")
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
