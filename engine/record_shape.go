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
	lookup := m.grammar().ArgumentLookup
	if lookup == nil {
		return Match{}, Match{}
	}
	container, key := lookup(m)
	if container.Is(Identifier) && key.LiteralKind() == "string" {
		return container, key
	}
	return Match{}, Match{}
}

func (m Match) ReturnsRecordBag() bool {
	value := m.returnedValue()
	function := m.Closest(Function)
	if owner := m.grammar().RecordReturnOwner; owner != nil {
		function = owner(m)
		if !function.Exists() {
			return false
		}
		value = m
	}
	if function.ReturnType().Exists() && !function.ReturnType().isLooseRecord() {
		return false
	}
	if typed := function.ResultType(); typed != nil && typed.Kind == "map" && len(typed.Args) == 2 && typed.Args[1].Kind == "named" {
		return false
	}
	if len(value.recordKeys()) < 2 {
		return false
	}
	reactive := m.grammar().ReactiveRecord
	return reactive == nil || !reactive(value)
}

func (m Match) recordKeys() []string {
	read := m.grammar().RecordKeys
	if read == nil || !m.Exists() {
		return nil
	}
	keys := read(m)
	slices.Sort(keys)
	return slices.Compact(keys)
}

func (m Match) isLooseRecord() bool {
	if accepts := m.grammar().LooseRecordType; accepts != nil {
		return accepts(m)
	}
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
	if read := m.grammar().ValueParameters; read != nil {
		return read(m)
	}
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
	read := m.grammar().NamedConstructor

	return read != nil && read(m)
}

func (m Match) clumpOwner() string {
	if owner := m.methodOwner(); owner != "" {
		return owner
	}

	return m.File()
}
