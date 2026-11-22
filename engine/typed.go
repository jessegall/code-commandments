package engine

import (
	"github.com/jessegall/code-commandments/contract"
)

func (m Match) ResultType() *contract.Type {
	if m.node == nil || !m.Is(Function) {
		return nil
	}

	return m.Child("returnType").Typed()
}

// Typed is the type of the node: the one the compiler resolved, else the one of its declared type or, for a
// function, its return type.
func (m Match) Typed() *contract.Type {
	if m.node == nil {
		return nil
	}
	if m.node.Resolved != nil {
		return m.node.Resolved
	}
	if declared := m.Child("declaredType"); declared.Exists() {
		return declared.Typed()
	}
	if m.Kind() == "function" {
		return m.Child("returnType").Typed()
	}

	return nil
}

func (m Match) ElementType() *contract.Type {
	typed := m.Typed()

	switch {
	case typed == nil:
		return m.elementItsLanguageKnows()
	case typed.Element != nil:
		return typed.Element
	case typed.Kind == "array" && len(typed.Args) == 1:
		return typed.Args[0]
	case typed.Kind == "map" && len(typed.Args) == 2:
		return typed.Args[1]
	}

	return m.elementItsLanguageKnows()
}

func (m Match) elementItsLanguageKnows() *contract.Type {
	return nil
}

func (m Match) AdmitsAbsence() bool {
	if m.HasFlag("optional") {
		return true
	}
	if declared := m.Child("declaredType").Typed(); declared != nil {
		return declared.Nullable != nil && *declared.Nullable
	}
	typed := m.Typed()

	return typed != nil && typed.Nullable != nil && *typed.Nullable
}
