package golang_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

// flows is a module whose branches the engine's own readings are asked about, one shape per reading: a guard with
// an else that says nothing the return did not, the same guard with the else dedented, a loop whose whole body is
// one condition, a loop that picks the one item it wanted and leaves, and a ladder comparing one status with the
// constants of a defined type.
var flows = map[string]string{
	"go.mod": "module example.com/flows\n",
	"orders/orders.go": `package orders

type Status int

const (
	Draft Status = iota
	Paid
	Shipped
	Refunded
)

type Order struct {
	Status Status
	Lines  []Line
	Note   string
}

type Line struct {
	Quantity int
	Cents    int
}

func (o Order) labelled() string {
	if o.Status == Draft {
		return "draft"
	} else {
		return "placed"
	}
}

func (o Order) dedented() string {
	if o.Status == Draft {
		return "draft"
	}

	return "placed"
}

func (o Order) billed() int {
	total := 0
	for _, line := range o.Lines {
		if line.Quantity > 0 {
			total += line.Cents
			total -= line.Cents / 10
		}
	}

	return total
}

func (o Order) firstPaid() int {
	for _, line := range o.Lines {
		if line.Quantity > 0 {
			total := line.Cents
			return total
		}
	}

	return 0
}

func (o Order) badge() string {
	if o.Status == Draft {
		return "grey"
	} else if o.Status == Paid {
		return "green"
	} else if o.Status == Shipped {
		return "blue"
	} else if o.Status == Refunded {
		return "red"
	}

	return ""
}

func (o Order) mixed() string {
	if o.Status == Draft {
		return "grey"
	} else if o.Note == "" {
		return "blank"
	} else if len(o.Lines) == 0 {
		return "empty"
	} else if o.Status == Paid {
		return "green"
	}

	return ""
}

func (o Order) guarded() string {
	if o.Note == "" {
		return "none"
	}
	if len(o.Lines) == 0 {
		return "empty"
	}

	return o.Note
}
`,
}

// TestGosBranchesAreReadByTheEnginesOwnReadings holds Go to the readings every language is held to: the engine
// says what a redundant else, a loop's sole guard and a subject ladder are, and Go's grammar only says where its
// tree files a body, an else and a condition. A Go answer that differs from another language's would mean the
// grammar is wrong, never that Go needs a reading of its own.
func TestGosBranchesAreReadByTheEnginesOwnReadings(t *testing.T) {
	codebase := codebaseOf(t, flows)

	for name, want := range map[string]bool{"labelled": true, "dedented": false, "guarded": false, "badge": false} {
		if held := funcNamed(t, codebase, name); engine.HasRedundantElse(branchOf(held)) != want {
			t.Errorf("%s reads as carrying a redundant else: %v", name, !want)
		}
	}

	for name, want := range map[string]bool{"billed": true, "firstPaid": false, "guarded": false} {
		var found bool
		for _, held := range funcNamed(t, codebase, name).Descendants() {
			if engine.IsSoleLoopBodyGuard(held) {
				found = true
			}
		}
		if found != want {
			t.Errorf("%s reads as wrapping its loop's body in one condition: %v", name, found)
		}
	}

	// A ladder is counted in rungs, and how many rungs make a ladder worth reporting is the detector's own
	// calibration, so one comparison is a ladder of one and a chain over four different subjects is no ladder at all.
	for name, want := range map[string]int{"badge": 4, "mixed": 0, "dedented": 1, "guarded": 1} {
		if held := engine.SubjectLadderLength(branchOf(funcNamed(t, codebase, name))); held != want {
			t.Errorf("%s reads as a ladder of %d rungs, where it is %d", name, held, want)
		}
	}
}

// TestALadderRungIsCountedOnceFromItsTop holds the ladder to the rule that keeps one ladder from being reported
// four times: a branch written as another's else is a rung, not a ladder of its own.
func TestALadderRungIsCountedOnceFromItsTop(t *testing.T) {
	codebase := codebaseOf(t, flows)

	var ladders int
	for _, held := range funcNamed(t, codebase, "badge").Descendants() {
		if engine.SubjectLadderLength(held) > 0 {
			ladders++
		}
	}
	if ladders != 1 {
		t.Errorf("one ladder of four rungs reads as %d ladders", ladders)
	}
}

// branchOf is the first branch the function's body holds.
func branchOf(function engine.Match) engine.Match {
	for _, held := range function.Descendants() {
		if held.Is(engine.Branch) {
			return held
		}
	}

	return engine.Match{}
}
