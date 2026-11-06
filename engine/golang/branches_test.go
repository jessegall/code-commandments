package golang_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

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

	for name, want := range map[string]int{"badge": 4, "mixed": 0, "dedented": 1, "guarded": 1} {
		if held := engine.SubjectLadderLength(branchOf(funcNamed(t, codebase, name))); held != want {
			t.Errorf("%s reads as a ladder of %d rungs, where it is %d", name, held, want)
		}
	}
}

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

func branchOf(function engine.Match) engine.Match {
	for _, held := range function.Descendants() {
		if held.Is(engine.Branch) {
			return held
		}
	}

	return engine.Match{}
}
