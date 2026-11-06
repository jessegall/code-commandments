package hooks

import (
	"encoding/json"
	"testing"
	"time"
)

func TestTheWindowOnTheLineIsReadApartFromAbsentAndNull(t *testing.T) {
	for _, told := range []struct {
		what    string
		fields  map[string]any
		window  time.Duration
		limited bool
	}{
		{"no window at all", map[string]any{}, DeclaredWindow, true},
		{"a null window", map[string]any{"window_ms": nil}, 0, false},
		{"a number", map[string]any{"window_ms": float64(5000)}, 5 * time.Second, true},
		{"a whole number", map[string]any{"window_ms": 1500}, 1500 * time.Millisecond, true},
		{"a number as text", map[string]any{"window_ms": "2500"}, 2500 * time.Millisecond, true},
		{"a json number", map[string]any{"window_ms": json.Number("750")}, 750 * time.Millisecond, true},
		{"a zero", map[string]any{"window_ms": float64(0)}, 0, true},
		{"words", map[string]any{"window_ms": "soon"}, DeclaredWindow, true},
	} {
		window, limited := windowIn(told.fields)

		if window != told.window || limited != told.limited {
			t.Errorf("%s is read as %s limited=%v, and it is %s limited=%v",
				told.what, window, limited, told.window, told.limited)
		}
	}
}

func TestANullWindowMeansNoLimitAndNotNoTime(t *testing.T) {
	unlimited := merging{window: 0, limited: false}
	if unlimited.deadline() <= 0 {
		t.Errorf("a null window gives a deadline of %s, so every delivery would pass unjudged", unlimited.deadline())
	}

	zero := merging{window: 0, limited: true}
	if zero.deadline() > 0 {
		t.Errorf("a window given as zero gives a deadline of %s, and zero means there is no time", zero.deadline())
	}
}

func TestTheRoleOnTheLineDecidesWhetherAWorkerIsJudged(t *testing.T) {
	for _, told := range []struct {
		role   string
		judged bool
	}{
		{"", true},
		{"main", true},
		{"orchestrator", true},
		{"worker", false},
		{"Worker", false},
		{" senior ", false},
		{"medior", false},
		{"reviewer", true},
	} {
		work := merging{role: roleIn(map[string]any{"role": told.role})}

		if work.judgedByRole() != told.judged {
			t.Errorf("the role %q is judged=%v, and it should be %v", told.role, work.judgedByRole(), told.judged)
		}
	}
}

func TestAnAgentEditingInsideAWorktreeIsStillTheMainAgent(t *testing.T) {
	work := mergeOf(map[string]any{
		"event": "worker_merging",
		"data":  map[string]any{"worktree": "/project/.journal/worktrees/development/worker-1", "role": "main"},
	})

	if !work.judgedByRole() {
		t.Error("a main agent editing inside a worktree is read as a worker, which is what the checkout's kind cannot tell and the role can")
	}
}
