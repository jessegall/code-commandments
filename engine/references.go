package engine

// Constructed is the node naming the type the construction creates; no node for anything else.
func (m Match) Constructed() Match {
	if read := m.lists().Constructs; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

// Referrers are the nodes referring to the declaration from outside it: every node whose name resolves to its
// symbol or whose call reaches it, and the calls its language's call graph finds. A declaration's references
// to itself, such as a recursive call, are no referrers; a declaration with no symbol has none.
func (m Match) Referrers() []Match {
	if m.node == nil || m.node.Facts == nil || m.node.Symbol == "" {
		return nil
	}

	found := referrers(m.file.Codebase())[m.node.Symbol]
	if read := m.lists().Callers; read != nil {
		found = append(found, read(m)...)
	}

	var outside []Match
	seen := map[any]bool{}
	for _, referrer := range found {
		if seen[referrer.node] || referrer.node == m.node || m.isAbove(referrer) {
			continue
		}

		seen[referrer.node] = true
		outside = append(outside, referrer)
	}

	return outside
}

// isAbove says whether the node sits somewhere below this one.
func (m Match) isAbove(below Match) bool {
	for ancestor := below.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		if ancestor.node == m.node {
			return true
		}
	}

	return false
}

// IsRead says whether the parameter's name is read anywhere in its function, a closure inside it included.
func (m Match) IsRead() bool {
	function := m.Closest(Function)
	for _, below := range function.Descendants() {
		if below.Is(Identifier) && below.Name() == m.Name() && below.node != m.node && !m.isAbove(below) {
			return true
		}
	}

	return false
}

// referrers indexes every node of the codebase by the symbol it refers to or the call reaches.
func referrers(c *Codebase) map[string][]Match {
	return Analysis(c, "referrers", func(c *Codebase) map[string][]Match {
		index := map[string][]Match{}
		for _, file := range c.files {
			for _, node := range file.Nodes() {
				if node.Facts == nil {
					continue
				}

				if node.Refers != "" {
					index[node.Refers] = append(index[node.Refers], Match{node: node, file: file})
				}

				if node.Target != nil && node.Target.Symbol != "" && node.Target.Symbol != node.Refers {
					index[node.Target.Symbol] = append(index[node.Target.Symbol], Match{node: node, file: file})
				}
			}
		}

		return index
	})
}
