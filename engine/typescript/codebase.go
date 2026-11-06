package typescript

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the TypeScript part of a codebase, modules and the <script> blocks of .vue files alike, the twin of the backend Codebase's selectors: typescript.In(codebase).WhereField().Where(...).
type Codebase struct {
	*engine.Codebase
}

func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.TypeScript, contract.Vue)}
}

func (c *Codebase) WhereField() *engine.Query {
	return c.WhereKind("PropertyDeclaration")
}

func (c *Codebase) WhereMemberAccess() *engine.Query {
	return c.WhereKind("PropertyAccessExpression")
}

func (c *Codebase) WhereFunction() *engine.Query {
	return c.Where(engine.As(Node.IsFunction))
}

func (c *Codebase) WhereObjectType() *engine.Query {
	return c.Where(engine.As(Node.IsObjectType))
}
