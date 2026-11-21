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

