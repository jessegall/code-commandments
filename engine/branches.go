package engine

import "slices"

func HasRedundantElse(branch Match) bool {
	if !branch.Is(Branch) || branch.IsContinuation() {
		return false
	}
	otherwise := branch.Otherwise()
	if len(otherwise) == 0 || (len(otherwise) == 1 && otherwise[0].IsContinuation()) {
		return false
	}
	body := branch.Body()

	return len(body) > 0 && body[len(body)-1].Is(BailOut)
}

func IsSoleLoopBodyGuard(branch Match) bool {
	if !branch.Is(Branch) || !branch.Condition().Exists() || len(branch.Otherwise()) > 0 {
		return false
	}
	if branch.BindsItsOwnTest() {
		return false
	}
	work := branch.Body()
	if len(work) < 2 || work[len(work)-1].Is(BailOut) {
		return false
	}
	loop := branch.Parent()
	if loop.Is(Block) {
		loop = loop.Parent()
	}
	if !loop.Is(Loop) {
		return false
	}
	body := loop.Body()

	return len(body) == 1 && body[0].Node() == branch.Node()
}

func LadderRungs(branch Match) []Match {
	rungs := []Match{branch}
	for held := branch; ; {
		otherwise := held.Otherwise()
		if len(otherwise) != 1 || !otherwise[0].IsContinuation() {
			return rungs
		}
		held = otherwise[0]
		rungs = append(rungs, held)
	}
}

func SubjectLadderLength(branch Match) int {
	if !branch.Is(Branch) || branch.IsContinuation() {
		return 0
	}
	rungs := LadderRungs(branch)
	subject := branch.Condition().ComparisonSubject()
	for _, rung := range rungs {
		tested := rung.Condition().ComparisonSubject()
		if !tested.Exists() || !tested.SameSyntax(subject) {
			return 0
		}
	}

	return len(rungs)
}

func (m Match) BindsItsOwnTest() bool {
	if read := m.grammar().BindsItsOwnTest; read != nil && m.node != nil {
		return read(m)
	}
	condition := m.Condition()

	return declaresAName(append(condition.Descendants(), condition))
}

func VariedSide(left, right Match, constant func(Match) bool) Match {
	switch {
	case !constant(left) && constant(right):
		return left
	case constant(left) && !constant(right):
		return right
	}

	return Match{}
}

func EndsEveryBranchAlike(branch Match) bool {
	if !branch.Is(Branch) || branch.IsContinuation() {
		return false
	}
	rungs := LadderRungs(branch)
	arms := [][]Match{rungs[len(rungs)-1].Otherwise()}
	for _, rung := range rungs {
		arms = append(arms, rung.Body())
	}
	if slices.ContainsFunc(arms, func(arm []Match) bool { return len(arm) == 0 }) {
		return false
	}
	tail := arms[0][len(arms[0])-1]

	return !slices.ContainsFunc(arms[1:], func(arm []Match) bool { return !arm[len(arm)-1].SameSyntax(tail) })
}

func IsUnreachable(statement Match) bool {
	if !statement.Exists() || statement.Is(Function) || statement.Is(TypeDeclaration) || statement.isInert() {
		return false
	}
	siblings := statement.Parent().ChildrenIn(statement.Field())
	at := slices.IndexFunc(siblings, func(sibling Match) bool { return sibling.Node() == statement.Node() })
	if at < 1 || slices.ContainsFunc(siblings, Match.IsJumpTarget) {
		return false
	}
	before := siblings[at-1]

	return before.Is(BailOut) && !IsUnreachable(before)
}

func (m Match) IsJumpTarget() bool {
	read := m.grammar().JumpTarget

	return read != nil && m.node != nil && read(m)
}

func (m Match) isInert() bool {
	return len(m.Children()) == 0 && !m.Is(BailOut)
}

func IsEvaluated(m Match) bool {
	if m.Role() != "expression" {
		return false
	}
	for around := m.Parent(); around.Exists(); around = around.Parent() {
		if around.Role() == "type" {
			return false
		}
	}

	return true
}

func IsConditionalExpression(m Match) bool {
	return m.Is(Branch) && IsEvaluated(m) && m.Condition().Exists()
}

func ResultIsDiscarded(m Match) bool {
	return IsEvaluated(m) && m.Parent().Is(ExpressionStatement)
}

func IsShortCircuitStatement(m Match) bool {
	return m.Is(Conjunction) && ResultIsDiscarded(m)
}

func IsTernaryStatement(m Match) bool {
	return IsConditionalExpression(m) && ResultIsDiscarded(m)
}

func IsOutermostNestedTernary(m Match) bool {
	if !IsConditionalExpression(m) {
		return false
	}
	for at := m.Parent(); at.Exists() && !at.Is(Function); at = at.Parent() {
		if IsConditionalExpression(at) {
			return false
		}
	}
	condition := m.Condition().Node()

	return slices.ContainsFunc(m.Children(), func(arm Match) bool {
		return arm.Node() != condition && slices.ContainsFunc(append([]Match{arm}, arm.Descendants()...), func(inner Match) bool {
			return IsConditionalExpression(inner) && !inner.crossesAFunctionBelow(m)
		})
	})
}

func (m Match) crossesAFunctionBelow(top Match) bool {
	for at := m.Parent(); at.Exists() && at.Node() != top.Node(); at = at.Parent() {
		if at.Is(Function) {
			return true
		}
	}

	return false
}
