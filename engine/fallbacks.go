package engine

var equalities = []string{"==", "!=", "===", "!==", "is", "is not"}

var orderComparisons = []string{"<", "<=", ">", ">="}

func (m Match) Fallback() Match {
	if m.operator() == "??" {
		written := m.Children()
		if len(written) == 2 {
			return written[1]
		}
	}

	return Match{}
}

func (m Match) Grouped() Match {
	return m
}
