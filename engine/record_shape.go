package engine

import (
	"slices"
	"strings"
)

func (m Match) ReadsRecordBag() bool {
	if !m.Is(Parameter) || !m.isLooseRecord() {
		return false
	}
	if validates := m.grammar().ValidatesRecord; validates != nil && validates(m) {
		return false
	}
	keys := map[string]bool{}
	for _, node := range m.DeclaringFunction().OwnDescendants() {
		container, key := node.recordFieldLookup()
		if key.Exists() && container.Name() == m.Name() {
			name, _ := key.Text()
			keys[name] = true
		}
	}
	return len(keys) >= 2
}

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

func (m Match) ReceivesRecordBag() bool {
	if !m.Is(Parameter) || !m.isLooseRecord() {
		return false
	}
	function := m.DeclaringFunction()
	position := slices.IndexFunc(function.Parameters(), func(parameter Match) bool { return parameter.node == m.node })
	if position < 0 {
		return false
	}
	var signature []string
	count := 0
	for _, call := range function.Referrers() {
		if !call.Is(Call) || call.IsTest() {
			continue
		}
		arguments := call.Arguments()
		if position >= len(arguments) {
			return false
		}
		keys := arguments[position].recordKeys()
		if len(keys) < 2 || signature != nil && !slices.Equal(signature, keys) {
			return false
		}
		signature = keys
		count++
	}
	return count >= 2
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

// DataClumps is every method taking the same three or more scalar values as a method of another owner: one concept
// travelling as loose parameters. A constructor, an override and a named constructor take the shape their role or
// their parent gives them, so none of them counts.
func DataClumps(codebase *Codebase) []Match {
	var signatures []string
	bySignature := map[string][]Match{}
	for _, method := range codebase.WhereFunction().
		Reject(Match.IsConstructor).
		Reject(Match.IsOverride).
		Reject(Match.IsNamedConstructor).
		Get() {
		signature := method.ValueSignature()
		if len(signature) < 3 {
			continue
		}
		key := strings.Join(signature, ", ")
		if bySignature[key] == nil {
			signatures = append(signatures, key)
		}
		bySignature[key] = append(bySignature[key], method)
	}
	var findings []Match
	for _, signature := range signatures {
		owners := map[string]bool{}
		for _, method := range bySignature[signature] {
			owners[method.clumpOwner()] = true
		}
		if len(owners) >= 2 {
			findings = append(findings, bySignature[signature]...)
		}
	}

	return findings
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
