package engine

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

func (m Match) HasNullableCallback() bool {
	if read := m.grammar().NullableCallback; read != nil {
		return read(m)
	}
	if !m.Is(Function) {
		return false
	}
	for _, parameter := range m.Parameters() {
		if !parameter.AdmitsAbsence() || !callableType(parameter.Typed(), m.grammar().CallableTypes) {
			continue
		}
		if defaultValue := parameter.DefaultValue(); defaultValue.Exists() && !defaultValue.IsNullish() {
			continue
		}
		if normalizesAndCalls(m.OwnDescendants(), parameter.Name()) {
			return true
		}
	}
	return false
}

func (m Match) DefaultValue() Match {
	if read := m.grammar().DefaultValue; read != nil {
		return read(m)
	}
	return Match{}
}

func callableType(typed *contract.Type, names []string) bool {
	if typed == nil {
		return false
	}
	if typed.Kind == "function" || typed.Kind == "callable" {
		return true
	}
	if slices.ContainsFunc(names, func(name string) bool {
		return typed.Name == name || strings.HasPrefix(typed.Name, name+"<")
	}) {
		return true
	}
	return typed.Kind == "union" && slices.ContainsFunc(typed.Members, func(member *contract.Type) bool {
		return callableType(member, names)
	})
}

func normalizesAndCalls(nodes []Match, name string) bool {
	called, guarded := false, false
	for _, node := range nodes {
		if node.Is(Call) && node.callbackTarget().Is(Identifier) && node.callbackTarget().Name() == name {
			called = true
			guarded = guarded || node.Is(NullSafe) || node.Parent().Is(NullSafe)
		}
		condition := node.Condition()
		if node.IsBranchingOnAbsence(name) || condition.Is(Identifier) && condition.Name() == name {
			guarded = true
		}
	}
	return called && guarded
}

func (m Match) callbackTarget() Match {
	if read := m.grammar().CallbackTarget; read != nil {
		return read(m)
	}
	return m.calledNode()
}

func (m Match) IsNullish() bool {
	return m.LiteralKind() == "null" || m.LiteralKind() == "undefined"
}

func (m Match) IsBranchingOnAbsence(name string) bool {
	parts := m.Children()
	if len(parts) != 2 || !parts[0].Is(Identifier) && !parts[1].Is(Identifier) {
		return false
	}
	if m.Is(Comparison) && slices.Contains(equalities, m.Operator()) {
		return parts[0].Name() == name && parts[1].IsNullish() || parts[1].Name() == name && parts[0].IsNullish()
	}
	return m.Fallback().Exists() && parts[0].Name() == name
}
