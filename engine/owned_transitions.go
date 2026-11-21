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
	explicit := m.grammar().HasAlternative
	return len(m.Otherwise()) > 0 || explicit != nil && explicit(m)
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
	return node.IsConstant() && typed != nil && typed.Kind == "named"
}

func (m Match) HandRollsRegistry() bool {
	grammar := m.grammar()
	if !m.Is(TypeDeclaration) || grammar.ArgumentLookup == nil || m.HasModifier("abstract") {
		return false
	}
	var fields []string
	if grammar.RegistryFields != nil {
		fields = grammar.RegistryFields(m)
		if len(fields) == 0 {
			return false
		}
	}
	written := map[string]bool{}
	guarded := map[string]bool{}
	for _, method := range m.Methods() {
		for _, node := range method.OwnDescendants() {
			if target := registryWrite(node, &grammar); target.Exists() {
				if store, key := grammar.ArgumentLookup(target); variableRegistryKey(key, &grammar) {
					written[registryField(store)] = true
				}
			}
			recordMissingKeys(node, &grammar, guarded)
		}
	}
	for store := range written {
		if store != "" && guarded[store] && (grammar.RegistryFields == nil || slices.Contains(fields, store)) {
			return true
		}
	}
	return false
}

func registryWrite(node Match, grammar *Grammar) Match {
	if grammar.KeyedWrite != nil {
		return grammar.KeyedWrite(node)
	}
	if target, value := node.assignmentSides(); value.Exists() {
		return target
	}
	return Match{}
}

func variableRegistryKey(key Match, grammar *Grammar) bool {
	if grammar.VariableKey != nil {
		return grammar.VariableKey(key)
	}
	return key.Is(Identifier)
}

func registryField(store Match) string {
	if store.Is(MemberAccess) && store.Receiver().isSelfReceiver() {
		return store.MemberName()
	}
	return ""
}

func recordMissingKeys(node Match, grammar *Grammar, guarded map[string]bool) {
	if node.Operator() == "??" {
		if operands := node.Children(); len(operands) == 2 && operands[1].Is(Throw) {
			if store, key := grammar.ArgumentLookup(operands[0]); key.Exists() {
				guarded[registryField(store)] = true
			}
		}
	}
	if !node.Is(Branch) || !node.OnlyThrows() {
		return
	}
	if grammar.MissingKeys != nil {
		condition := node.Condition()
		for _, test := range append([]Match{condition}, condition.OwnDescendants()...) {
			for _, store := range grammar.MissingKeys(test) {
				guarded[registryField(store)] = true
			}
		}
		return
	}
	for _, lookup := range node.OwnDescendants() {
		if store, key := grammar.ArgumentLookup(lookup); variableRegistryKey(key, grammar) {
			guarded[registryField(store)] = true
		}
	}
}
