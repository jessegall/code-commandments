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
		!prose.IsThirdPerson(m.Name()) || prose.ReadsAsQuestion(m.Name()) {
		return false
	}
	return slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool { return fieldOnSelf(node).Exists() })
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

func (m Match) RollsItsOwnWither() bool {
	body := m.Body()
	if !m.ownsMethodName() || len(body) != 1 {
		return false
	}
	built := body[0].returnedValue()
	owner := m.EnclosingType()
	if !owner.Exists() || !built.Is(Construction) ||
		BareSymbol(built.Constructed().Name()) != owner.Name() && BareSymbol(built.TypeName()) != owner.Name() {
		return false
	}
	carried := map[string]bool{}
	changed := false
	for _, argument := range built.Arguments() {
		if argument.Is(MemberAccess) && argument.Receiver().isSelfReceiver() {
			carried[argument.MemberName()] = true
		} else {
			changed = true
		}
	}
	return changed && len(carried) >= 2
}

func (m Match) LooksUpAnOwnedField() bool {
	read := m.grammar().ArgumentLookup
	if read == nil || !m.Is(Function) {
		return false
	}
	body := m.Body()
	if len(body) != 1 {
		return false
	}
	container, key := read(body[0].returnedValue())
	if !container.Exists() || !key.Is(MemberAccess) || key.Receiver().isSelfReceiver() {
		return false
	}
	receiver := key.Receiver()
	if !receiver.Is(Identifier) || !slices.ContainsFunc(m.Parameters(), func(parameter Match) bool {
		return parameter.Name() == receiver.Name()
	}) {
		return false
	}
	return receiver.hasLocalTypeDeclaration()
}

func (m Match) hasLocalTypeDeclaration() bool {
	return m.typeDeclaration().Exists()
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

func (m Match) RestoresScratchField() bool {
	read := m.grammar().InitializedValue
	if !m.Is(Function) || read == nil {
		return false
	}
	type savedField struct {
		name   string
		writes int
	}
	saved := map[string]savedField{}
	writes := map[string]int{}
	for _, node := range m.OwnDescendants() {
		left, right := node.assignmentSides()
		if initial := read(node); initial.Is(MemberAccess) && initial.Receiver().isSelfReceiver() {
			name := node.DeclaredName()
			if name == "" && node.Binds() && left.Is(Identifier) {
				name = left.Name()
			}
			if name != "" {
				field := initial.MemberName()
				saved[name] = savedField{name: field, writes: writes[field]}
			}
		}
		if !left.Is(MemberAccess) || !left.Receiver().isSelfReceiver() {
			continue
		}
		field := left.MemberName()
		original := saved[right.Name()]
		if right.Is(Identifier) && original.name == field && writes[field] > original.writes {
			return true
		}
		writes[field]++
	}
	return false
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
