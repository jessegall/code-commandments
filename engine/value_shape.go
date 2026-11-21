package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

func (m Match) IsNarratedCommand() bool {
	if !m.ownsMethodName() || !prose.IsThirdPerson(m.Name()) || prose.ReadsAsQuestion(m.Name()) {
		return false
	}
	return m.hasNoResult() && slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool {
		return node.Is(Call) || node.Is(Assignment)
	})
}

func (m Match) IsBareStatePredicate() bool {
	if !m.ownsMethodName() || len(m.Parameters()) > 0 || !m.returnsBoolean() ||
		!m.HasBareStatePredicateName() {
		return false
	}
	return slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool { return fieldOnSelf(node).Exists() })
}

func (m Match) HasBareStatePredicateName() bool {
	return prose.IsBareStateName(m.Name())
}

func (m Match) ownsMethodName() bool {
	return m.Is(Function) && m.methodOwner() != "" && !m.IsConstructor() && !m.IsImplicit() && !m.IsOverride()
}

func (m Match) methodOwner() string {
	if read := m.grammar().MethodOwner; read != nil {
		return read(m)
	}
	return m.EnclosingType().Identity()
}

func (m Match) hasNoResult() bool {
	if result := m.ResultType(); result != nil && result.Text != "" {
		return result.Text == "void" || result.Text == "None"
	}
	return !slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool { return node.returnedValue().Exists() })
}

func (m Match) returnsBoolean() bool {
	typed := m.ResultType()
	return typed != nil && slices.Contains(m.grammar().BooleanTypes, typed.Text)
}

func (m Match) returnedValue() Match {
	if !m.Is(Return) {
		return Match{}
	}
	if read := m.grammar().ReturnedValue; read != nil {
		return read(m)
	}
	children := m.Children()
	if len(children) == 1 {
		return children[0]
	}
	return Match{}
}

func (m Match) hasLocalTypeDeclaration() bool {
	declaration := m.typeDeclaration()
	return declaration.Exists() && !declaration.IsInDependency()
}

func (m Match) typeDeclaration() Match {
	typed := m.Typed()
	if typed == nil {
		typed = m.Declared().Typed()
	}
	return m.declaresType(typed)
}

func (m Match) declaresType(typed *contract.Type) Match {
	if typed != nil && typed.Kind == "pointer" {
		typed = typed.Element
	}
	if types, known := m.Types(); known {
		for _, name := range typeNames(m, typed) {
			if declaration, found := types.Declaration(name); found {
				return declaration
			}
		}
	}
	return Match{}
}

func typeNames(m Match, typed *contract.Type) []string {
	names := []string{m.TypeName(), m.Refers()}
	if typed != nil && typed.Kind == "named" {
		names = append(names, typed.Name, typed.Text)
	}
	return names
}

func (m Match) assignmentSides() (Match, Match) {
	if !m.Is(Assignment) {
		return Match{}, Match{}
	}
	if read := m.grammar().AssignmentSides; read != nil {
		return read(m)
	}
	parts := m.Children()
	if len(parts) == 2 {
		return parts[0], parts[1]
	}
	return Match{}, Match{}
}

func ReadPair(left, right string) func(Match) (Match, Match) {
	return func(node Match) (Match, Match) { return node.Child(left), node.Child(right) }
}
