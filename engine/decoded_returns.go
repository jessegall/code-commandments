package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

func IsUntypedCollection(typed *contract.Type) bool {
	value := typed.Element
	if value == nil && (typed.Kind == "map" || typed.Kind == "array") && len(typed.Args) > 0 {
		value = typed.Args[len(typed.Args)-1]
	}
	if value == nil {
		return false
	}
	return slices.Contains([]string{"any", "unknown", "object", "interface{}"}, value.Text)
}

func (m Match) ReturnsRawDecodedValue() bool {
	grammar := m.grammar()
	if grammar.DecodedDestination != nil {
		return m.returnsDecodedDestination(grammar)
	}
	if grammar.Decode == nil || !m.Is(Call) || !m.Grouped().Parent().Is(Return) || !grammar.Decode(m) {
		return false
	}
	function := m.EnclosingFunction()
	if function.IsOverride() || (grammar.UntypedReturn != nil && !grammar.UntypedReturn(function)) {
		return false
	}
	return grammar.Encode == nil || !slices.ContainsFunc(m.Arguments(), grammar.Encode)
}

func (m Match) returnsDecodedDestination(grammar Grammar) bool {
	if !m.Is(Identifier) || !m.Parent().Is(Return) || (grammar.UntypedReturn != nil && !grammar.UntypedReturn(m)) {
		return false
	}
	for _, call := range m.EnclosingFunction().OwnDescendants() {
		if call.Node().Span.Start >= m.Node().Span.Start || !call.Is(Call) {
			continue
		}
		if destination := grammar.DecodedDestination(call); destination.Is(Identifier) && destination.SameSyntax(m) {
			return grammar.Encode == nil || !slices.ContainsFunc(call.Arguments(), grammar.Encode)
		}
	}

	return false
}
