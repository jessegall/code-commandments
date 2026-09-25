package typescript

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the TypeScript part of a codebase, modules and the <script> blocks of .vue files alike, the
// twin of the backend Codebase's selectors: typescript.In(codebase).WhereField().Where(...).
type Codebase struct {
	*engine.Codebase
}

// In is the TypeScript part of the codebase: its .ts files and its .vue files, whose scripts are TypeScript.
func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.TypeScript, contract.Vue)}
}

// WhereField opens a query over every field a class declares.
func (c *Codebase) WhereField() *engine.Query {
	return c.WhereKind("PropertyDeclaration")
}

// WhereMemberAccess opens a query over every property read: a.b and a?.b.
func (c *Codebase) WhereMemberAccess() *engine.Query {
	return c.WhereKind("PropertyAccessExpression")
}

// WhereFunction opens a query over every named function: a function, a method, a constructor, an accessor,
// and a variable declared as an arrow.
func (c *Codebase) WhereFunction() *engine.Query {
	return c.Where(engine.As(Node.IsFunction))
}

// WhereObjectType opens a query over every declared object shape: an interface, or a type alias of an object.
func (c *Codebase) WhereObjectType() *engine.Query {
	return c.Where(engine.As(Node.IsObjectType))
}
