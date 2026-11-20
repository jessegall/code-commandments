package engine

import (
	"slices"
	"strings"
)

func (m Match) ReadsRecordBag() bool {
	lookup := m.grammar().ArgumentLookup
	if !m.Is(Parameter) || lookup == nil || !m.isLooseRecord() {
		return false
	}
	if validates := m.grammar().ValidatesRecord; validates != nil && validates(m) {
		return false
	}
	keys := map[string]bool{}
	for _, node := range m.DeclaringFunction().OwnDescendants() {
		container, key := lookup(node)
		if container.Is(Identifier) && container.Name() == m.Name() && key.LiteralKind() == "string" {
			name, _ := key.Text()
			keys[name] = true
		}
	}
	return len(keys) >= 2
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
	function := m.Closest(Function)
	if function.ReturnType().Exists() && !function.ReturnType().isLooseRecord() {
		return false
	}
	if typed := function.ResultType(); typed != nil && typed.Kind == "map" && len(typed.Args) == 2 && typed.Args[1].Kind == "named" {
		return false
	}
	value := m.returnedValue()
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
	typed := m.Typed()
	if typed == nil {
		return false
	}
	if typed.Kind == "map" && len(typed.Args) == 2 {
		return typed.Args[0].Text == "string"
	}
	return strings.HasPrefix(typed.Text, "Record<string,")
}

func DataClumps(codebase *Codebase) []Match {
	functions := codebase.WhereFunction().Where(func(method Match) bool { return method.methodOwner() != "" }).Get()
	clumps := map[string][]Match{}
	for _, method := range functions {
		var parts []string
		for _, parameter := range method.Parameters() {
			if parameter.isScalarType() && parameter.Name() != "" {
				parts = append(parts, parameter.Name()+":"+parameter.Typed().Text)
			}
		}
		slices.Sort(parts)
		for first := 0; first+2 < len(parts); first++ {
			for second := first + 1; second+1 < len(parts); second++ {
				for third := second + 1; third < len(parts); third++ {
					key := strings.Join([]string{parts[first], parts[second], parts[third]}, ",")
					clumps[key] = append(clumps[key], method)
				}
			}
		}
	}
	found := map[Match]bool{}
	for _, methods := range clumps {
		owner := methods[0].methodOwner()
		if !slices.ContainsFunc(methods, func(method Match) bool { return method.methodOwner() != owner }) {
			continue
		}
		for _, method := range methods {
			found[method] = true
		}
	}
	var findings []Match
	for _, method := range functions {
		if found[method] {
			findings = append(findings, method)
		}
	}
	return findings
}
