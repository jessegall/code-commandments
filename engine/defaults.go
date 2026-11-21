package engine

import (
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

type blankField [2]string

func (m Match) HasFieldBlankReplacement(name string) bool {
	return fieldsWhoseBlankIsReplaced(m.Codebase())[blankField{m.EnclosingType().Identity(), name}]
}

func fieldsWhoseBlankIsReplaced(codebase *Codebase) map[blankField]bool {
	return Analysis(codebase, "fields-whose-blank-is-replaced", func(codebase *Codebase) map[blankField]bool {
		fields := map[blankField]bool{}
		for _, read := range codebase.WhereIs(MemberAccess).Get() {
			replacement := read.grammar().BlankReplacement
			if replacement == nil {
				continue
			}
			grouped := read.Grouped()
			subject, fallback := replacement(grouped.Parent())
			if subject.node != grouped.node || !fallback.Exists() || fallback.IsBlankString() || subject.SameSyntax(fallback) {
				continue
			}
			owner := read.Declaring().Identity()
			if owner != "" {
				fields[blankField{owner, read.NameRead()}] = true
			}
		}
		return fields
	})
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
