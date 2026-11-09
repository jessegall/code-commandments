package hooks

import "testing"

func TestAWorkersEditIsJudgedByTheSettingItsOwnLineCarries(t *testing.T) {
	moment, read := Moment4Of(map[string]any{
		"v": float64(1), "event": "file_edited", "role": "worker", "environment": "story-engine",
		"settings": map[string]any{workersKey: true, workerEditsKey: true},
		"data":     map[string]any{"path": "/nowhere/order.py"},
	})
	if !read {
		t.Fatal("a file edit line was not read as a moment")
	}

	if said := notForThisRole(MomentOf(moment)); said != "" {
		t.Errorf("a worker's edit in an environment whose line says %s is true was left unjudged: %s", workersKey, said)
	}

	moment["settings"] = map[string]any{workersKey: false}
	if said := notForThisRole(MomentOf(moment)); said == "" {
		t.Errorf("a worker's edit in an environment whose line says %s is false was judged", workersKey)
	}
}

func TestASwitchIsOnWhetherItIsWrittenAsAFlagOrAsText(t *testing.T) {
	for value, want := range map[any]bool{true: true, false: false, "true": true, "false": false, "": false, nil: false} {
		if got := switchedOn(value); got != want {
			t.Errorf("%#v reads as %v, want %v", value, got, want)
		}
	}
}
