package engine

import "testing"

func TestTheWorkAJudgingDoesIsCountedNotTimed(t *testing.T) {
	before := Counted()

	query := &Query{selected: func(yield func(Match) bool) {
		for range 5 {
			if !yield(Match{}) {
				return
			}
		}
	}}

	query.Where(func(Match) bool { return true })
	query.Where(func(Match) bool { return false })
	query.Get()

	did := Since(before)

	if did.Nodes != 5 {
		t.Errorf("a query over five nodes counted %d", did.Nodes)
	}

	if did.Checks != 10 {
		t.Errorf("two checks over five nodes counted %d, and the second check is never reached where the first fails", did.Checks)
	}

	if Since(Counted()).Nodes != 0 {
		t.Error("the counts do not stand still between two readings")
	}
}
