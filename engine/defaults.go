package engine

import (
	"slices"
	"strconv"
)

func (m Match) IsBlankString() bool {
	text, present := m.Text()
	return m.LiteralKind() == "string" && present && text == ""
}

func (m Match) DeclaresString() bool {
	typed := m.Typed()
	return typed != nil && (typed.Name == "string" || typed.Text == "string")
}

func (m Match) TestsBlankDefault() bool {
	if read := m.grammar().BlankDefault; read != nil {
		return read(m)
	}
	if !m.DeclaresString() || !m.DefaultValue().IsBlankString() {
		return false
	}
	owner := m.EnclosingType()
	if m.Is(Parameter) {
		owner = m.EnclosingFunction()
	}
	name := m.DeclaredName()
	return name != "" && slices.ContainsFunc(owner.Descendants(), func(test Match) bool {
		if condition := test.Condition(); condition.NameRead() == name && (condition.Is(Identifier) || fieldOnSelf(condition).Exists()) {
			return true
		}
		parts := test.Children()
		return len(parts) == 2 && test.Is(Comparison) && slices.Contains(equalities, test.Operator()) &&
			(parts[0].NameRead() == name && parts[1].IsBlankString() || parts[1].NameRead() == name && parts[0].IsBlankString())
	})
}

func (m Match) ManufacturesArgument() bool {
	if read := m.grammar().ManufacturesFill; read != nil {
		return read(m)
	}
	fallback := m.Fallback()
	if !fallback.IsEmptyScalar() {
		return false
	}
	argument := m.Grouped()
	call := argument.Parent()
	if !call.Is(Call) && !call.Is(Construction) {
		return false
	}
	for position, handed := range call.Arguments() {
		if handed.node == argument.node {
			return !call.parameterAt(position).DefaultValue().SameSyntax(fallback)
		}
	}
	return false
}

func (m Match) IsEmptyScalar() bool {
	if m.LiteralKind() == "bool" {
		return m.IsAbsence()
	}
	value, literal := m.Text()
	if !literal {
		return false
	}
	switch m.LiteralKind() {
	case "string":
		return value == ""
	case "int", "float":
		number, err := strconv.ParseFloat(value, 64)
		return err == nil && number == 0
	}
	return false
}

func (call Match) parameterAt(position int) Match {
	for _, target := range call.Codebase().Declarations(call.TargetSymbol()) {
		if parameters := target.Parameters(); position < len(parameters) {
			return parameters[position]
		}
	}
	return Match{}
}

func (m Match) DiscardsOptionalReturn() bool {
	if read := m.grammar().DeNulledFinder; read != nil {
		return read(m)
	}
	typed := m.ReturnType()
	if !m.Is(Function) || !typed.AdmitsAbsence() || typed.DeclaresString() || m.IsOverride() {
		return false
	}
	callers := slices.DeleteFunc(m.Referrers(), func(call Match) bool { return !call.Is(Call) || call.IsTest() })
	return len(callers) >= 2 && !slices.ContainsFunc(callers, func(call Match) bool { return !call.forcesPresence() })
}

func (m Match) forcesPresence() bool {
	parent := m.Grouped().Parent()
	if read := parent.grammar().NullForgiving; read != nil && read(parent) {
		return true
	}
	if parent.Is(NullSafe) {
		return false
	}
	if parent.Fallback().ThrowWithin().Exists() {
		return true
	}
	return parent.Is(MemberAccess) && parent.Receiver().node == m.node
}

func (m Match) CoalescesLoopSubject() bool {
	if read := m.grammar().CoalescedLoop; read != nil {
		return read(m)
	}
	read := m.grammar().Iterated
	if read == nil || !m.Parent().Is(Loop) || read(m.Parent()).node != m.node || !m.Fallback().IsEmptyCollection() {
		return false
	}
	parts := m.Children()
	if len(parts) < 2 {
		return false
	}
	root := parts[0]
	for receiver := root.Receiver(); receiver.Exists(); receiver = root.Receiver() {
		root = receiver
	}
	return root.Is(Identifier) && slices.ContainsFunc(m.EnclosingFunction().Parameters(), func(parameter Match) bool {
		return parameter.Name() == root.Name()
	})
}

func (m Match) IsConditionalSpread() bool {
	grammar := m.grammar()
	if grammar.ConditionalSpread != nil {
		return grammar.ConditionalSpread(m)
	}
	if grammar.Spreads == nil || grammar.ConditionalArms == nil || !grammar.Spreads(m.Grouped().Parent()) {
		return false
	}
	one, other := grammar.ConditionalArms(m)
	if one.IsEmptyCollection() {
		one, other = other, one
	}
	return other.IsEmptyCollection() && !one.IsEmptyCollection() && (one.IsLiteralCollection() || one.ElementType() != nil)
}

func (m Match) IsLiteralCollection() bool {
	read := m.grammar().LiteralCollection
	return read != nil && read(m)
}

func (m Match) FillsDataWithPlaceholder() bool {
	read := m.grammar().PlaceholderData
	return read != nil && read(m)
}
