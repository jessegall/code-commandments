package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

type PropertyTypes func(*Codebase, string, string) *contract.Type

func OffersPropertyTypes(language contract.Language, read PropertyTypes) {
	registers(language, func(row *spoken) { row.properties = append(slices.Clone(row.properties), read) })
}

func (c *Codebase) PropertyType(language contract.Language, class, field string) *contract.Type {
	for _, read := range spokenIn(language).properties {
		if inferred := read(c, class, field); inferred != nil {
			return inferred
		}
	}
	return nil
}

func (m Match) ResultType() *contract.Type {
	if m.node == nil || !m.Is(Function) {
		return nil
	}
	if typed := m.node.Returns; typed != nil {
		return typed
	}
	return m.ReturnType().Typed()
}

func (m Match) Typed() *contract.Type {
	if m.node == nil {
		return nil
	}

	switch {
	case m.node.Resolved != nil:
		return m.node.Resolved
	case m.node.Declared != nil:
		return m.node.Declared
	case m.node.Returns != nil:
		return m.node.Returns
	}

	return m.writtenFor()
}

func (m Match) writtenFor() *contract.Type {
	declaration := m.Parent()
	if returned := declaration.ReturnType(); returned.node == m.node {
		return declaration.node.Returns
	}

	if written := declaration.ParameterType(); written.node == m.node {
		return declaration.node.Declared
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
	if read := m.grammar().ElementOf; read != nil && m.node != nil {
		return read(m)
	}

	return nil
}

func (m Match) AdmitsAbsence() bool {
	if m.HasFlag("optional") {
		return true
	}
	if m.node != nil && m.node.Declared != nil {
		return m.node.Declared.Nullable
	}
	typed := m.Typed()

	return typed != nil && typed.Nullable
}
