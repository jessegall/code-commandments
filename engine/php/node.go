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

func (n Node) IsThrownWithMessage() bool {
	if n.Kind() != "Expr_New" || !n.Up().IsThrow() {
		return false
	}
	arguments := Arguments(n.Match)
	if len(arguments) == 0 {
		return false
	}
	message := arguments[0].Child("value").Kind()

	return message == "Scalar_String" || message == "Scalar_InterpolatedString"
}

func (n Node) IsSwallowedCatch() bool {
	if n.Kind() != "Stmt_Catch" {
		return false
	}
	body := n.ChildrenIn("stmts")
	if len(body) == 0 {
		return true
	}
	if len(body) != 1 || body[0].Kind() != "Stmt_Return" {
		return false
	}

	return Node{Match: body[0].Child("expr")}.IsAbsenceValue()
}

func (n Node) RethrowsUnchanged() bool {
	body := n.ChildrenIn("stmts")
	caught := n.Child("var")
	if n.Kind() != "Stmt_Catch" || len(body) != 1 || caught.Kind() != "Expr_Variable" {
		return false
	}
	if !n.isTheLastCatch() {
		return false
	}
	thrown := Node{Match: body[0]}.thrownAlone()

	return thrown.Kind() == "Expr_Variable" && thrown.Name() == caught.Name()
}

func (n Node) isTheLastCatch() bool {
	catches := n.Up().ChildrenIn("catches")

	return len(catches) > 0 && catches[len(catches)-1].Node() == n.Node()
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
		if child.Node().Field == "types" {
			types = append(types, child.Name())
		}
	}

	return types
}

func (n Node) IsRethrowWithoutCause() bool {
	if n.Kind() != "Expr_New" || !n.Up().IsThrow() {
		return false
	}
	catch := n.Closest(engine.Catch)
	caught := catch.Child("var")
	if catch.Kind() != "Stmt_Catch" || caught.Kind() != "Expr_Variable" || caught.Name() == "" {
		return false
	}
	for _, argument := range Arguments(n.Match) {
		for _, variable := range append([]engine.Match{argument}, descendantsOf(argument)...) {
			if variable.Kind() == "Expr_Variable" && variable.Name() == caught.Name() {
				return false
			}
		}
	}

	return true
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
