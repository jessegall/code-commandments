package rule

import (
	"slices"
	"sort"
	"strings"
)

// cost is what asking a step of one node really costs: a comparison on the node, a pattern over its text, a walk around it, or a question for the whole program; a rule is compiled cheapest first.
type cost int

const (
	ofTheNode cost = iota
	ofAPattern
	ofAWalk
	ofTheProgram
)

func (s Step) cost() cost {
	return max(s.ownCost(), s.reach())
}

func (s Step) ownCost() cost {
	switch {
	case s.walksTheProgram():
		return ofTheProgram
	case s.groups():
		return s.dearestOfItsGroup()
	case s.walksAround():
		return ofAWalk
	case s.pattern != nil, s.NameMood != "", s.NameCase != "":
		return ofAPattern
	default:
		return ofTheNode
	}
}

func (s Step) reach() cost {
	switch {
	case slices.Contains(targetsAnotherFile, s.Of):
		return ofTheProgram
	case s.Of == "", s.Of == "parent", strings.HasPrefix(s.Of, "child:"):
		return ofTheNode
	default:
		return ofAWalk
	}
}

func (s Step) groups() bool {
	return s.All != nil || s.Any != nil || s.Not != nil
}

func (s Step) dearestOfItsGroup() cost {
	dearest := ofTheNode

	for _, step := range slices.Concat(s.All, s.Any) {
		dearest = max(dearest, step.cost())
	}

	if s.Not != nil {
		dearest = max(dearest, s.Not.cost())
	}

	return dearest
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
		s.PassesItsElement != nil || s.LadderRungs != nil || s.TestCode != nil
}

func compiled(steps []Step) []Step {
	order := slices.Clone(steps)

	for at := range order {
		order[at].compileInside()
	}

	cheapestFirst(order)

	return order
}

func (s *Step) compileInside() {
	for _, nested := range s.nested() {
		nested.compileInside()
	}

	cheapestFirst(s.All)
	cheapestFirst(s.Any)
}

func cheapestFirst(steps []Step) {
	sort.SliceStable(steps, func(one, two int) bool { return steps[one].cost() < steps[two].cost() })
}

func (f find) compiled() find {
	f.Where = compiled(f.Where)
	f.Reject = compiled(f.Reject)

	return f
}
