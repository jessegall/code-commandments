package hooks

import (
	"encoding/json"
	"testing"
	"time"
)

func TestADeliveryWithNoWindowIsStillJudged(t *testing.T) {
	for _, each := range []struct {
		why   string
		given map[string]any
		want  time.Duration
	}{
		{"an event that names no window asks for the one this plugin declared", map[string]any{}, DeclaredWindow},
		{"a journal that names one is believed, however its number arrives", map[string]any{"window_ms": float64(5000)}, 5 * time.Second},
		{"and whether it arrives as a plain number", map[string]any{"window_ms": 5000}, 5 * time.Second},
		{"or as a decoder's own", map[string]any{"window_ms": json.Number("5000")}, 5 * time.Second},
	} {
		if got, _ := windowIn(each.given); got != each.want {
			t.Errorf("%s: %s, want %s", each.why, got, each.want)
		}
	}

	if DeclaredWindow < changedFilesWindow {
		t.Errorf("the declared window of %s is below the shallow threshold of %s, so a delivery is judged at no depth", DeclaredWindow, changedFilesWindow)
	}
}
