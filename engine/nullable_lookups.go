package engine

import "slices"

func (m Match) ReturnsNullableLookup() bool {
	grammar := m.grammar()
	if grammar.NullableLookup != nil {
		return grammar.NullableLookup(m)
	}
	if grammar.KeyedStore == nil || !m.Grouped().Parent().Is(Return) {
		return false
	}
	read := m
	if fallback := m.Fallback(); fallback.Exists() {
		if !fallback.IsNullish() {
			return false
		}
		read = m.Children()[0]
	}
	store := grammar.KeyedStore(read)
	function := m.EnclosingFunction()
	if !fieldOnSelf(store).Exists() || function.IsOverride() {
		return false
	}
	return !allCallersHandleAbsence(function)
}

func allCallersHandleAbsence(function Match) bool {
	callers := slices.DeleteFunc(function.Referrers(), func(caller Match) bool { return !caller.Is(Call) || caller.IsTest() })
	return len(callers) > 0 && !slices.ContainsFunc(callers, func(call Match) bool { return !call.acknowledgesAbsence() })
}

func (m Match) acknowledgesAbsence() bool {
	parent := m.Grouped().Parent()
	if parent.Is(NullSafe) || parent.Fallback().Exists() {
		return true
	}
	return parent.Is(Comparison) && slices.ContainsFunc(parent.Children(), Match.IsNullish)
}
