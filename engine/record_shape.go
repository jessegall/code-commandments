package engine

import (
	"slices"
	"strings"
)

func (m Match) RecordBagParameter() Match {
	container, key := m.recordFieldLookup()
	if !key.Exists() {
		return Match{}
	}
	for _, parameter := range m.Closest(Function).Parameters() {
		if parameter.Name() == container.Name() && parameter.isLooseRecord() {
			return parameter
		}
	}
	return Match{}
}

func (m Match) recordFieldLookup() (Match, Match) {
	return Match{}, Match{}
}

func (m Match) ReturnsRecordBag() bool {
	value := m.returnedValue()
	function := m.Closest(Function)
	if function.ReturnType().Exists() && !function.ReturnType().isLooseRecord() {
		return false
	}
	if typed := function.ResultType(); typed != nil && typed.Kind == "map" && len(typed.Args) == 2 && typed.Args[1].Kind == "named" {
		return false
	}
	if len(value.recordKeys()) < 2 {
		return false
	}
	return true
}

func (m Match) recordKeys() []string {
	return nil
}

func (m Match) isLooseRecord() bool {
	typed := m.Typed()
	if typed == nil {
		return false
	}
	if typed.Kind == "map" && len(typed.Args) == 2 {
		return typed.Args[0].Text == "string"
	}
	return strings.HasPrefix(typed.Text, "Record<string,")
}

// ValueSignature is a function's scalar parameters, each as its type and name, in sorted order.
func (m Match) ValueSignature() []string {
	var parts []string
	for _, parameter := range m.Parameters() {
		if parameter.isScalarType() && parameter.Name() != "" {
			parts = append(parts, parameter.Typed().Text+" "+parameter.Name())
		}
	}
	slices.Sort(parts)

	return parts
}

// IsNamedConstructor is a static method that builds its own type under a name: the parameters are the type's own.
func (m Match) IsNamedConstructor() bool {
	return false
}
