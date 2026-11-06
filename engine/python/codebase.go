package python

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the Python part of a codebase, opening queries over Python nodes only, the twin of the backend Codebase's selectors: python.In(codebase).WhereFunction().Where(...).
type Codebase struct {
	*engine.Codebase
	Program *Program
}

func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.Python), Program: Of(codebase)}
}

func (c *Codebase) WhereFunction() *engine.Query {
	return c.WhereKind("FunctionDef", "AsyncFunctionDef")
}

func (c *Codebase) WhereMethodDeclaration() *engine.Query {
	return c.WhereFunction().Where(engine.As(Node.IsMethod))
}

func (c *Codebase) WhereClass() *engine.Query {
	return c.WhereKind("ClassDef")
}

func (c *Codebase) WhereStatement() *engine.Query {
	return c.Where(engine.As(Node.IsStatement))
}

func (c *Codebase) WhereExpression() *engine.Query {
	return c.Where(engine.As(Node.IsEvaluated))
}

func (c *Codebase) WhereCall() *engine.Query {
	return c.WhereKind("Call")
}

func (n Node) IsStatement() bool {
	return n.Node().Role == "statement" || n.IsDefinition()
}

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
