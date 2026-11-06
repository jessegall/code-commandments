package engine

func (c *Codebase) Where(check Check) *Query {
	return c.nodes().Where(check)
}

func (c *Codebase) WhereKind(kinds ...string) *Query {
	queries.Add(1)

	return &Query{selected: func(yield func(Match) bool) {
		for _, kind := range kinds {
			for _, file := range c.opened() {
				for _, node := range file.Nodes() {
					if node.Kind == kind && !yield(Match{node: node, file: file}) {
						return
					}
				}
			}
		}
	}}
}

func (c *Codebase) WhereIs(neutral Neutral) *Query {
	return c.nodes().Where(func(m Match) bool { return m.Is(neutral) })
}

func (c *Codebase) WhereNew() *Query {
	return c.WhereIs(Construction)
}

func (c *Codebase) WhereCall() *Query {
	return c.WhereIs(Call)
}

func (c *Codebase) WhereFunction() *Query {
	return c.WhereIs(Function)
}

func (c *Codebase) WhereTypeDeclaration() *Query {
	return c.WhereIs(TypeDeclaration)
}

func (c *Codebase) WhereAssign() *Query {
	return c.WhereIs(Assignment)
}

func (c *Codebase) nodes() *Query {
	queries.Add(1)

	return &Query{selected: func(yield func(Match) bool) {
		for _, file := range c.opened() {
			for _, node := range file.Nodes() {
				if !yield(Match{node: node, file: file}) {
					return
				}
			}
		}
	}}
}
