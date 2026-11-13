package sidebyside

import (
	"sync/atomic"
	"testing"
)

// Every job runs once, and never more of them at once than the workers asked for.
func TestEachRunsEveryJobOnceAndNoMoreAtOnceThanAsked(t *testing.T) {
	var ran [50]atomic.Int32
	var running, most atomic.Int32
	Each(len(ran), 3, func(at int) {
		now := running.Add(1)
		for seen := most.Load(); now > seen && !most.CompareAndSwap(seen, now); seen = most.Load() {
		}
		ran[at].Add(1)
		running.Add(-1)
	})
	for at := range ran {
		if ran[at].Load() != 1 {
			t.Errorf("job %d ran %d times", at, ran[at].Load())
		}
	}
	if most.Load() > 3 {
		t.Errorf("%d jobs ran at once, where 3 were asked for", most.Load())
	}
}
