package engine

import (
	"slices"
	"strings"
)

func (m Match) ownsRecordBehavior(subject Match) bool {
	owns := m.grammar().OwnsRecordBehavior
	if owns == nil {
		return false
	}
	declaration := subject.typeDeclaration()

	return declaration.Source() == m.Source() && owns(declaration)
}

func (m Match) translatesBetweenModules(subject Match) bool {
	owner := m.moduleName()
	from := subject.typeDeclaration().moduleName()
	result := m.declaresType(m.ResultType())
	to := result.moduleName()
	if owner == "" || from == "" || to == "" || from == owner || to == owner || from == to {
		return false
	}
	return slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool {
		return node.Is(Construction) && node.typeDeclaration().Identity() == result.Identity()
	})
}

func (m Match) DeclaresValueEquality() bool {
	if !m.Is(TypeDeclaration) {
		return false
	}
	for _, method := range m.Methods() {
		if strings.EqualFold(method.Name(), "equals") && len(method.Parameters()) == 1 && method.returnsBoolean() {
			return true
		}
	}
	return false
}

