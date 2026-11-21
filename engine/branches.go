package engine

import "slices"

func (m Match) BindsItsOwnTest() bool {
	condition := m.Condition()

	return declaresAName(append(condition.Descendants(), condition))
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
