package php

import (
	"github.com/jessegall/code-commandments/contract"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Node is a match read in PHP's terms: the per-node questions a PHP detector asks, as the PHP engine's AstNode answers them.
type Node struct {
	engine.Match
}

func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

func (n Node) Up() Node {
	return Node{Match: n.Parent()}
}

func (n Node) IsThrow() bool {
	return n.Kind() == "Expr_Throw"
}

func (n Node) NewClassName() string {
	if n.Kind() != "Expr_New" || !isName(n.Child("class")) {
		return ""
	}

	return n.Child("class").Name()
}

func (n Node) ConstructedClass() string {
	if n.NewClassName() == "" {
		return ""
	}

	return namedClass(n.Match, n.Child("class"))
}

func (n Node) thrownAlone() Node {
	throw := Node{Match: n.Child("expr")}
	if n.Kind() != "Stmt_Expression" || !throw.IsThrow() {
		return Node{}
	}

	return Node{Match: throw.Child("expr")}
}

func (n Node) CaughtTypes() []string {
	if n.Kind() != "Stmt_Catch" {
		return nil
	}
	var types []string
	for _, child := range n.Children() {
		if child.Node().InRole() == "types" {
			types = append(types, child.Name())
		}
	}

	return types
}

func (n Node) IsAbsenceValue() bool {
	return !n.Exists() || n.IsNull() || n.IsFalse() || n.IsEmptyArrayLiteral()
}

func (n Node) IsNull() bool {
	return IsNullConstant(n.Match)
}

func (n Node) IsFalse() bool {
	return n.Kind() == "Expr_ConstFetch" && strings.EqualFold(n.Child("name").Name(), "false")
}

func (n Node) IsEmptyArrayLiteral() bool {
	return n.Kind() == "Expr_Array" && len(n.Children()) == 0
}

func In(codebase *engine.Codebase) *engine.Codebase {
	return codebase.Of(contract.PHP)
}

var genericFailures = []string{"Exception", "Error", "RuntimeException", "LogicException", "InvalidArgumentException", "DomainException",
	"UnexpectedValueException", "OutOfRangeException", "OutOfBoundsException", "RangeException", "LengthException"}
