package hooks

import (
	"testing"
)

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
