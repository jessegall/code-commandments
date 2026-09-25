package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Node is a match read in PHP's terms: the per-node questions a PHP detector asks, as the PHP engine's AstNode
// answers them. A question every language answers belongs on engine.Match instead.
type Node struct {
	engine.Match
}

// Decorate reads a match as a Node.
func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

// Up is the node's parent, read in PHP's terms.
func (n Node) Up() Node {
	return Node{Match: n.Parent()}
}

// IsThrow says whether the node is a throw expression.
func (n Node) IsThrow() bool {
	return n.Kind() == "Expr_Throw"
}

// NewClassName is the class a new expression names, or empty when it constructs a dynamic or anonymous class.
func (n Node) NewClassName() string {
	if n.Kind() != "Expr_New" || !isName(n.Child("class")) {
		return ""
	}

	return n.Child("class").Name()
}

// IsThrownWithMessage says whether the node constructs the exception a throw throws, handing it a message string.
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

// IsSwallowedCatch says whether a catch drops what it caught: an empty body, or one that returns an absence value.
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

// CaughtTypes is every class a catch names.
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

// IsRethrowWithoutCause says whether the node constructs the exception a throw inside a catch throws without
// passing on the exception the catch holds.
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

// IsAbsenceValue says whether the node is no expression at all, null, false or an empty array.
func (n Node) IsAbsenceValue() bool {
	return !n.Exists() || n.IsNull() || n.IsFalse() || n.IsEmptyArrayLiteral()
}

// IsNull says whether the node is the constant null.
func (n Node) IsNull() bool {
	return IsNullConstant(n.Match)
}

// IsFalse says whether the node is the constant false.
func (n Node) IsFalse() bool {
	return n.Kind() == "Expr_ConstFetch" && strings.EqualFold(n.Child("name").Name(), "false")
}

// IsEmptyArrayLiteral says whether the node is an array literal with no items.
func (n Node) IsEmptyArrayLiteral() bool {
	return n.Kind() == "Expr_Array" && len(n.Children()) == 0
}
