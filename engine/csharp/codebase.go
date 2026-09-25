package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the C# part of a codebase, opening queries over C# nodes only, the twin of the backend Codebase's
// selectors: csharp.In(codebase).WhereCall().Where(...).
type Codebase struct {
	*engine.Codebase
	Program *Program
}

// In is the C# part of the codebase.
func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.CSharp), Program: Of(codebase)}
}

// WhereType opens a query over every type declaration: class, record, struct, interface, enum.
func (c *Codebase) WhereType() *engine.Query {
	return c.Where(engine.As(Node.IsTypeDeclaration))
}

// WhereFunction opens a query over every member that runs a body: a method, constructor, accessor, operator or
// local function; a lambda is not one.
func (c *Codebase) WhereFunction() *engine.Query {
	return c.Where(engine.As(Node.RunsABody))
}

// WhereMethodDeclaration opens a query over every method.
func (c *Codebase) WhereMethodDeclaration() *engine.Query {
	return c.WhereKind("MethodDeclaration")
}

// WhereStatement opens a query over every statement: not the declarations, parameters and clauses the tree
// gives nodes of their own.
func (c *Codebase) WhereStatement() *engine.Query {
	return c.Where(engine.As(Node.IsStatement))
}

// WhereExpression opens a query over every expression.
func (c *Codebase) WhereExpression() *engine.Query {
	return c.Where(engine.As(Node.IsExpression))
}

// WhereCall opens a query over every invocation.
func (c *Codebase) WhereCall() *engine.Query {
	return c.WhereKind("InvocationExpression")
}

// RunsABody says whether the node is a member or local function with a body of its own.
func (n Node) RunsABody() bool {
	return n.FunctionBody().Exists() && !n.IsExpression()
}

// IsTypeDeclaration says whether the node declares a class, record, struct, interface or enum.
func (n Node) IsTypeDeclaration() bool {
	return n.Is("ClassDeclaration", "RecordDeclaration", "RecordStructDeclaration", "StructDeclaration", "InterfaceDeclaration", "EnumDeclaration")
}
