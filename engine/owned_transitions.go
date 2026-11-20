package engine

import "slices"

func (m Match) GuardsAnotherObjectsTransition() bool {
	if !m.Is(Branch) || !m.OnlyThrows() || m.IsContinuation() || len(m.Otherwise()) > 0 {
		return false
	}
	if alternative := m.grammar().HasAlternative; alternative != nil && alternative(m) {
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
	if read := node.grammar().EnumCase; read != nil {
		owner, _ := read(node)
		if owner != "" {
			return true
		}
	}
	if node.Is(MemberAccess) && node.Receiver().typeDeclaration().TypeKind() == "enum" {
		return true
	}
	typed := node.Typed()
	return node.Node() != nil && node.Node().Facts != nil && node.Node().Constant && typed != nil && typed.Kind == "named"
}

func (m Match) HandRollsRegistry() bool {
	read := m.grammar().ArgumentLookup
	if !m.Is(TypeDeclaration) || read == nil || m.HasModifier("abstract") {
		return false
	}
	written := map[string]bool{}
	guarded := map[string]bool{}
	for _, method := range m.Methods() {
		if method.IsConstructor() {
			continue
		}
		for _, node := range method.OwnDescendants() {
			left, right := node.assignmentSides()
			if left.Exists() && right.Exists() {
				if store, key := read(left); store.Is(MemberAccess) && store.Receiver().isSelfReceiver() && key.Is(Identifier) {
					written[store.MemberName()] = true
				}
			}
			if node.Is(Branch) && node.OnlyThrows() {
				for _, lookup := range node.OwnDescendants() {
					if store, key := read(lookup); store.Is(MemberAccess) && store.Receiver().isSelfReceiver() && key.Is(Identifier) {
						guarded[store.MemberName()] = true
					}
				}
			}
		}
	}
	for store := range written {
		if guarded[store] {
			return true
		}
	}
	return false
}
