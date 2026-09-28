package engine

// Ancestors is every node above this one, nearest first, up to and including the file's root.
func (m Match) Ancestors() []Match {
	var above []Match
	for ancestor := m.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		above = append(above, ancestor)
	}

	return above
}

// Siblings are the nodes filling the same field of the same parent as this one, itself among them, in
// source order: a statement's are the statements of its block, an argument's the call's other arguments.
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

// Next is the sibling right after this one; no node for the last.
func (m Match) Next() Match {
	return m.sibling(1)
}

// Previous is the sibling right before this one; no node for the first.
func (m Match) Previous() Match {
	return m.sibling(-1)
}

// sibling is the sibling offset places from this one; no node past either end.
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
