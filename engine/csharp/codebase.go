package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the C# part of a codebase, opening queries over C# nodes only, the twin of the backend Codebase's selectors: csharp.In(codebase).WhereCall().Where(...).
type Codebase struct {
	*engine.Codebase
	Program *Program
}

func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.CSharp), Program: Of(codebase)}
}

func (c *Codebase) WhereType() *engine.Query {
	return c.Where(engine.As(Node.IsTypeDeclaration))
}

func (c *Codebase) WhereFunction() *engine.Query {
	return c.Where(engine.As(Node.RunsABody))
}

func (c *Codebase) WhereMethodDeclaration() *engine.Query {
	return c.WhereKind("MethodDeclaration")
}

func (c *Codebase) WhereStatement() *engine.Query {
	return c.Where(engine.As(Node.IsStatement))
}

func (c *Codebase) WhereExpression() *engine.Query {
	return c.Where(engine.As(Node.IsExpression))
}

func (c *Codebase) WhereCall() *engine.Query {
	return c.WhereKind("InvocationExpression")
}

func (n Node) RunsABody() bool {
	return n.FunctionBody().Exists() && !n.IsExpression()
}

func (n Node) IsTypeDeclaration() bool {
	return n.Is("ClassDeclaration", "RecordDeclaration", "RecordStructDeclaration", "StructDeclaration", "InterfaceDeclaration", "EnumDeclaration")
}
