package engine

import "slices"

func (m Match) acknowledgesAbsence() bool {
	parent := m.Grouped().Parent()
	if parent.Is(NullSafe) || parent.Fallback().Exists() {
		return true
	}
	return parent.Is(Comparison) && slices.ContainsFunc(parent.Children(), Match.IsNullish)
}
