package engine

import "slices"

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
	arms := [][]Match{withoutInertTail(rungs[len(rungs)-1].Otherwise())}
	for _, rung := range rungs {
		arms = append(arms, withoutInertTail(rung.Body()))
	}
	if slices.ContainsFunc(arms, func(arm []Match) bool { return len(arm) == 0 }) {
		return false
	}
	tail := arms[0][len(arms[0])-1]

	return !slices.ContainsFunc(arms[1:], func(arm []Match) bool { return !arm[len(arm)-1].SameSyntax(tail) })
}

// withoutInertTail is an arm without the empty statements that close it, such as the one a parser keeps for a comment
// before the closing brace: the arm ends where its last real statement does.
func withoutInertTail(arm []Match) []Match {
	for len(arm) > 0 && arm[len(arm)-1].isInert() {
		arm = arm[:len(arm)-1]
	}

	return arm
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

func (m Match) crossesAFunctionBelow(top Match) bool {
	for at := m.Parent(); at.Exists() && at.Node() != top.Node(); at = at.Parent() {
		if at.Is(Function) {
			return true
		}
	}

	return false
}

func IsChoice(m Match) bool {
	return (m.Is(Branch) || m.Is(Loop)) && !m.IsContinuation() && !IsEvaluated(m)
}

func (m Match) ChoicesAbove() int {
	return m.choicesAbove(IsChoice)
}

func (m Match) choicesAbove(choice func(Match) bool) int {
	choices := 0
	for _, above := range m.Ancestors() {
		if above.Is(Function) {
			break
		}
		if choice(above) {
			choices++
		}
	}

	return choices
}

func (m Match) Steps() []Match {
	if read := m.grammar().Steps; read != nil && m.node != nil {
		return read(m)
	}

	return nil
}

func (m Match) AdvancesACounter() bool {
	if slices.Contains([]string{"++", "--", "+=", "-="}, m.operator()) {
		return true
	}
	written := m.Children()
	if !m.Is(Assignment) || len(written) < 2 {
		return false
	}
	if !slices.Contains([]string{"+", "-"}, written[1].operator()) {
		return written[1].StepsFrom(written[0])
	}

	return slices.ContainsFunc(written[1].Children(), written[0].SameSyntax)
}

func (m Match) StepsFrom(start Match) bool {
	arguments := m.Arguments()

	return m.Is(Call) && m.Receiver().SameSyntax(start) && len(arguments) > 0 && !slices.ContainsFunc(arguments, func(argument Match) bool { return !argument.IsConstant() })
}

