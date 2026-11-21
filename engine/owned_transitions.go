package engine

import "slices"

func (m Match) GuardsAnotherObjectsTransition() bool {
	if !m.Is(Branch) || !m.OnlyThrows() || m.IsContinuation() || m.hasAlternative() {
		return false
	}
	receiver := stateComparedReceiver(m.Condition())
	if !receiver.Exists() || receiver.isSelfReceiver() {
		return false
	}
	for _, sibling := range m.Siblings() {
		if sibling.node.Span.Start >= m.node.Span.End {
			call := statementValue(sibling)
			if call.Is(Call) && !call.Is(NullSafe) && call.Receiver().SameSyntax(receiver) {
				return true
			}
		}
	}
	return false
}

func (m Match) hasAlternative() bool {
	return len(m.Otherwise()) > 0
}

func (m Match) OnlyThrows() bool {
	body := m.Body()
	return len(body) == 1 && statementThrows(body[0])
}

func stateComparedReceiver(condition Match) Match {
	parts := slices.DeleteFunc(condition.Children(), func(child Match) bool { return child.Field() == "ops" })
	if len(parts) != 2 {
		return Match{}
	}
	if condition.Is(Conjunction) {
		left, right := stateComparedReceiver(parts[0]), stateComparedReceiver(parts[1])
		if left.SameSyntax(right) {
			return left
		}
	}
	if !condition.Is(Comparison) || !slices.Contains([]string{"==", "===", "!=", "!==", "<>", "is", "is not"}, condition.Operator()) {
		return Match{}
	}
	for at, field := range parts {
		if field.Is(MemberAccess) && !field.Is(NullSafe) && readsStateCase(parts[1-at]) {
			return field.Receiver()
		}
	}
	return Match{}
}

func readsStateCase(node Match) bool {
	if node.Is(MemberAccess) && node.Receiver().typeDeclaration().TypeKind() == "enum" {
		return true
	}
	typed := node.Typed()
	return node.IsConstant() && typed != nil && typed.Kind == "named"
}

func (m Match) HandRollsRegistry() bool {
	return false
}
