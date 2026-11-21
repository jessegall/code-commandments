package engine

const (
	conditionParts    = 3
	conditionSubjects = 2
)

func isDecisionCondition(m Match) bool {
	parent := m.Parent()
	if assertion := m.grammar().TestAssertion; assertion != nil && m.IsTest() {
		if body := parent.Body(); len(body) == 1 && assertion(statementValue(body[0])) {
			return false
		}
	}
	if m.IsRenderCondition() {
		return true
	}

	return m.EnclosingFunction().Exists() && (parent.Is(Branch) || parent.Is(Loop)) && !guardsFromTheTop(parent)
}

func (m Match) IsRenderCondition() bool {
	reads := m.grammar().RenderCondition

	return m.Exists() && reads != nil && reads(m.Parent()).node == m.node
}

func (m Match) IsRenderConditionPart() bool {
	part := m.Grouped()
	for part.Parent().Is(Conjunction) {
		part = part.Parent().Grouped()
	}

	return part.IsRenderCondition()
}

func guardsFromTheTop(branch Match) bool {
	if !onlyLeaves(branch) {
		return false
	}
	for _, statement := range branch.Parent().ChildrenIn(branch.Field()) {
		if statement.Node() == branch.Node() {
			return true
		}
		if statement.Binds() || statement.Is(Import) || says(statement) {
			continue
		}
		if !statement.Is(Branch) || !onlyLeaves(statement) {
			return false
		}
	}

	return false
}

func says(statement Match) bool {
	if !statement.Is(ExpressionStatement) && !statement.Is(Literal) {
		return false
	}
	for _, held := range append(statement.Children(), statement) {
		if held.Is(Literal) {
			return true
		}
	}

	return false
}

func (m Match) OnlyLeaves() bool {
	return onlyLeaves(m)
}

func onlyLeaves(branch Match) bool {
	for _, child := range branch.Children() {
		if child.Is(BailOut) {
			return true
		}
		if !child.Is(Block) {
			continue
		}
		body := child.Children()
		if len(body) == 1 && body[0].Is(BailOut) {
			return true
		}
	}

	return false
}

func conditionChainOf(m Match) []Match {
	var parts []Match
	for _, child := range m.Children() {
		if child.Is(Conjunction) {
			parts = append(parts, conditionChainOf(child)...)

			continue
		}
		parts = append(parts, child)
	}

	return parts
}

func declaresAName(parts []Match) bool {
	for _, part := range parts {
		for _, held := range append(part.Descendants(), part) {
			if held.Declares() {
				return true
			}
		}
	}

	return false
}

func subjectsOf(parts []Match) []string {
	var subjects []string
	for _, part := range parts {
		for _, sent := range append(part.Descendants(), part) {
			if !sent.Is(Call) && !sent.Is(MemberAccess) {
				continue
			}
			root := rootOf(sent.Receiver())
			if !root.Exists() || root.Refers() != "" {
				continue
			}
			if subject := root.Written(); subject != "" && !contains(subjects, subject) {
				subjects = append(subjects, subject)
			}
		}
	}

	return subjects
}

func rootOf(receiver Match) Match {
	if receiver.Is(Identifier) || receiver.Is(SelfReference) {
		return receiver
	}
	for _, held := range receiver.Descendants() {
		if held.Is(Identifier) || held.Is(SelfReference) {
			return held
		}
	}

	return Match{}
}

func contains(subjects []string, subject string) bool {
	for _, counted := range subjects {
		if counted == subject {
			return true
		}
	}

	return false
}
