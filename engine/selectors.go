package engine

import "slices"

// Where opens a query over every node the check answers yes to.
func (c *Codebase) Where(check Check) *Query {
	return c.nodes().Where(check)
}

// WhereKind opens a query over the nodes of the language's own kinds, such as Expr_MethodCall.
func (c *Codebase) WhereKind(kinds ...string) *Query {
	return c.nodes().Where(func(m Match) bool { return slices.Contains(kinds, m.Kind()) })
}

// WhereIs opens a query over the nodes that answer a neutral kind.
func (c *Codebase) WhereIs(neutral Neutral) *Query {
	return c.nodes().Where(func(m Match) bool { return m.Is(neutral) })
}

// WhereNew opens a query over every construction: new, object creation, a call of a class.
func (c *Codebase) WhereNew() *Query {
	return c.WhereIs(Construction)
}

// WhereCall opens a query over every call: function, method, static.
func (c *Codebase) WhereCall() *Query {
	return c.WhereIs(Call)
}

// WhereFunction opens a query over every function-like with a body.
func (c *Codebase) WhereFunction() *Query {
	return c.WhereIs(Function)
}

// WhereTypeDeclaration opens a query over every declaration of a type.
func (c *Codebase) WhereTypeDeclaration() *Query {
	return c.WhereIs(TypeDeclaration)
}

// WhereAssign opens a query over every assignment, compound or not.
func (c *Codebase) WhereAssign() *Query {
	return c.WhereIs(Assignment)
}

// nodes is a query over every node of every file, in file and pre-order.
func (c *Codebase) nodes() *Query {
	return &Query{selected: func(yield func(Match) bool) {
		for _, file := range c.files {
			for _, node := range file.Nodes() {
				if !yield(Match{node: node, file: file}) {
					return
				}
			}
		}
	}}
}
