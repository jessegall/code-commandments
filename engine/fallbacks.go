package engine

import "slices"

var equalities = []string{"==", "!=", "===", "!==", "is", "is not"}

func (m Match) Fallback() Match {
	if m.operator() == "??" {
		written := m.Children()
		if len(written) == 2 {
			return written[1]
		}
	}
	if read := m.grammar().Fallback; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

func (m Match) Grouped() Match {
	for read := m.grammar().Grouping; read != nil && m.Parent().Exists() && read(m.Parent()); {
		m = m.Parent()
	}

	return m
}

func IsComparedToItsFallback(m Match) bool {
	fallback := m.Fallback()
	if fallback.IsNullish() {
		return false
	}
	stable := m.grammar().StableFallback
	accepted := fallback.Is(Literal) || fallback.IsConstant()
	if stable != nil {
		accepted = stable(fallback)
	}
	if !accepted {
		return false
	}
	compared := m.Grouped()
	comparison := compared.Parent()
	if !comparison.Is(Comparison) || !slices.Contains(equalities, comparison.operator()) {
		return false
	}

	return slices.ContainsFunc(comparison.Children(), func(side Match) bool {
		return side.node != compared.node && side.SameSyntax(fallback)
	})
}
