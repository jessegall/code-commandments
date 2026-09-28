package engine

// Ancestors is every node above this one, nearest first, up to and including the file's root.
func (m Match) Ancestors() []Match {
	var above []Match
	for ancestor := m.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		above = append(above, ancestor)
	}

	return above
}

// IsContinuation says whether the branch continues the one it sits in rather than nesting inside it: an else-if,
// however its language's tree holds one.
func (m Match) IsContinuation() bool {
	read := m.lists().Continues

	return read != nil && m.node != nil && read(m)
}

// Siblings are the nodes filling the same field of the same parent as this one, itself among them, in
// source order: a statement's are the statements of its block. A value a language wraps as an argument, as PHP's
// Arg and C#'s Argument do, is its wrapper's only child.
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
