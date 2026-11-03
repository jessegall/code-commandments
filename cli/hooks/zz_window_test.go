package hooks

import (
	"encoding/json"
	"testing"
	"time"
)

// TestADeliveryWithNoWindowIsStillJudged holds the fault that let every delivery the journal ever sent pass
// unjudged. The journal's `worker_delivering` carries `worker`, `task`, `branch`, `worktree` and `files` — and no
// `window_ms`, because the HOLD is ours to declare and it honoured it without repeating it. Read as zero, the
// window fell through both depths and the answer was "clean", so a worker's work was never judged while the
// manifest promised three minutes of judging.
func TestADeliveryWithNoWindowIsStillJudged(t *testing.T) {
	for _, each := range []struct {
		why   string
		given any
		want  time.Duration
	}{
		{"an event that names no window asks for the one this plugin declared", nil, DeclaredWindow},
		{"a journal that names one is believed, however its number arrives", float64(5000), 5 * time.Second},
		{"and whether it arrives as a plain number", 5000, 5 * time.Second},
		{"or as a decoder's own", json.Number("5000"), 5 * time.Second},
	} {
		if got := windowOf(each.given); got != each.want {
			t.Errorf("%s: %s, want %s", each.why, got, each.want)
		}
	}

	// And the window it falls back to must be enough to judge in at all, or the fallback is no better than zero.
	if DeclaredWindow < changedFilesWindow {
		t.Errorf("the declared window of %s is below the shallow threshold of %s, so a delivery is judged at no depth", DeclaredWindow, changedFilesWindow)
	}
}
