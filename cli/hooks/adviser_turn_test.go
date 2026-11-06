package hooks

import (
	"testing"
	"time"
)

func TestOneAgentsTurnNeverWaitsOnAnothers(t *testing.T) {
	held := make(chan struct{})
	done := make(chan int, 2)

	advice := startAdvisers(func(given asked) {
		whose := MomentOf(given.given).Agent
		if whose == 1 {
			<-held
		}

		done <- whose
	})

	for _, whose := range []int{1, 2} {
		advice.add(asked{given: map[string]any{
			"event": "PostToolUse",
			"agent": map[string]any{"id": whose},
			"tool":  map[string]any{"name": "Edit", "file": "/project/src/order.py"},
		}, queued: true, first: true})
	}

	select {
	case whose := <-done:
		if whose != 2 {
			t.Fatalf("agent %d answered while agent 1 was held, which is the wrong one", whose)
		}
	case <-time.After(10 * time.Second):
		t.Fatal("the second agent's turn never came while the first was held, so one agent's work sits on another's back")
	}

	close(held)

	if whose := <-done; whose != 1 {
		t.Errorf("the held agent answered as %d", whose)
	}

	if len(advice.each) != 2 {
		t.Errorf("two agents were answered by %d adviser(s)", len(advice.each))
	}
}
