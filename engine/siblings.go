package engine

func (m Match) Ancestors() []Match {
	var above []Match
	for ancestor := m.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		above = append(above, ancestor)
	}

	return above
}

func (m Match) IsContinuation() bool {
	read := m.grammar().Continues

	return read != nil && m.node != nil && read(m)
}

func (m Match) Siblings() []Match {
	if m.node == nil {
		return nil
	}

	parent := m.Parent()
	if !parent.Exists() {
		return []Match{m}
	}

	return parent.ChildrenIn(m.node.Field)
}

func (m Match) Next() Match {
	return m.sibling(1)
}

func (m Match) Previous() Match {
	return m.sibling(-1)
}

func (m Match) sibling(offset int) Match {
	siblings := m.Siblings()

	for at, sibling := range siblings {
		if sibling.node != m.node {
			continue
		}

		if at+offset < 0 || at+offset >= len(siblings) {
			return Match{}
		}

		return siblings[at+offset]
	}

	return Match{}
}
