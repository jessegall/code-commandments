package python

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the Python part of a codebase, opening queries over Python nodes only, the twin of the backend
// Codebase's selectors: python.In(codebase).WhereFunction().Where(...).
type Codebase struct {
	*engine.Codebase
	Program *Program
}

// In is the Python part of the codebase.
func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.Python), Program: Of(codebase)}
}

// WhereFunction opens a query over every def: a module function, a method and a nested function alike; a lambda
// is not one.
func (c *Codebase) WhereFunction() *engine.Query {
	return c.WhereKind("FunctionDef", "AsyncFunctionDef")
}

// WhereMethodDeclaration opens a query over every def a class body declares.
func (c *Codebase) WhereMethodDeclaration() *engine.Query {
	return c.WhereFunction().Where(engine.As(Node.IsMethod))
}

// WhereClass opens a query over every class.
func (c *Codebase) WhereClass() *engine.Query {
	return c.WhereKind("ClassDef")
}

// WhereStatement opens a query over every statement, definitions included.
func (c *Codebase) WhereStatement() *engine.Query {
	return c.Where(engine.As(Node.IsStatement))
}

// WhereExpression opens a query over every expression the code evaluates, nested ones included; a type
// annotation is read as a type, not evaluated, so nothing inside one is.
func (c *Codebase) WhereExpression() *engine.Query {
	return c.Where(engine.As(Node.IsEvaluated))
}

// WhereCall opens a query over every call.
func (c *Codebase) WhereCall() *engine.Query {
	return c.WhereKind("Call")
}

// IsStatement says whether the node is a statement or a definition.
func (n Node) IsStatement() bool {
	return n.Node().Role == "statement" || n.IsDefinition()
}

// IsEvaluated says whether the node is an expression the code evaluates, outside every type annotation.
func (n Node) IsEvaluated() bool {
	if n.Node().Role != "expression" {
		return false
	}
	for around := n.Parent(); around.Exists(); around = around.Parent() {
		if around.Node().Role == "type" {
			return false
		}
	}

	return true
}
