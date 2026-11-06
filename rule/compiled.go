package rule

import "sort"

// cost is what asking a step of one node really costs, in the only three sizes that matter: a local read of the
// node itself, a walk of what lies under or around it, and a question nothing but the whole program can answer.
// A rule is compiled into this order so the order it was written in can never cost more than it has to.
type cost int

const (
	ofTheNode cost = iota
	ofAWalk
	ofTheProgram
)

func (s Step) cost() cost {
	switch {
	case s.walksTheProgram():
		return ofTheProgram
	case s.walksAround():
		return ofAWalk
	default:
		return ofTheNode
	}
}

func (s Step) walksTheProgram() bool {
	return s.CalledFrom != "" || s.ReferredFrom != nil || s.Callers != nil || s.DeclaredBy != "" ||
		s.DeclaredElsewhere != nil || s.AnsweredBy != nil || s.Unused != nil || s.Duplicated != nil ||
		s.Repeated != nil || s.SameAs != nil || s.Overrides != nil || s.IsA != "" || s.Implicit != nil ||
		s.Layer != "" || s.Nullable != nil || s.TypeOrigin != "" || s.DocReference != nil || s.Rule != ""
}

func (s Step) walksAround() bool {
	return s.Descendant != nil || s.Child != nil || s.Body != nil || s.Inside != nil || s.Ancestors != nil ||
		s.Next != nil || s.Previous != nil || s.Sibling != "" || s.Documents != nil || s.Counts != nil ||
		s.Parameters != nil || s.Arguments != nil || s.Argument != nil || s.Members != nil || s.Calls != nil ||
		s.Complexity != nil || s.NestedAtLeast != nil || s.Lines != nil || s.RedundantElse != nil ||
		s.UnnamedCondition != nil || s.BindsItsOwnTest != nil || s.SoleLoopBodyGuard != nil ||
		s.PassesItsElement != nil || s.LadderRungs != nil || s.TestCode != nil || s.All != nil || s.Any != nil ||
		s.Not != nil
}

func compiled(steps []Step) []Step {
	if len(steps) < 2 {
		return steps
	}

	order := make([]Step, len(steps))
	copy(order, steps)

	sort.SliceStable(order, func(one, two int) bool { return order[one].cost() < order[two].cost() })

	return order
}

func (f find) compiled() find {
	f.Where = compiled(f.Where)
	f.Reject = compiled(f.Reject)

	return f
}
