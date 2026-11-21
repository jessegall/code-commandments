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
	return nil
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

func (m Match) IsConditionalSpread() bool {
	return false
}

func (m Match) IsLiteralCollection() bool {
	return false
}

func (m Match) FillsDataWithPlaceholder() bool {
	return false
}
