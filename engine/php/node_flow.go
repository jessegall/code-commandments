package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsFunctionLike() bool {
	return slices.Contains(functionLikes, n.Kind())
}

func (n Node) EnclosingFunctionLike() Node {
	for at := n; at.Exists(); at = at.Up() {
		if at.IsFunctionLike() {
			return at
		}
	}

	return Node{}
}

func (n Node) IsLoopSubject() bool {
	return n.Exists() && n.Parent().Kind() == "Stmt_Foreach" && n.Node().InRole() == "expr"
}

func (n Node) IsCoalesce() bool {
	return n.Kind() == "Expr_BinaryOp_Coalesce"
}

func (n Node) CoalesceLeft() Node {
	if !n.IsCoalesce() {
		return Node{}
	}

	return Node{Match: n.Child("left")}
}

func (n Node) CoalesceRight() Node {
	if !n.IsCoalesce() {
		return Node{}
	}

	return Node{Match: n.Child("right")}
}

func (n Node) IsTernary() bool {
	return n.Kind() == "Expr_Ternary"
}

func (n Node) isShortTernary() bool {
	return n.IsTernary() && !n.Child("if").Exists()
}

func (n Node) FallsBackToEmptyCollection() bool {
	if n.IsCoalesce() {
		fallback := n.CoalesceRight()
		for fallback.IsCoalesce() {
			fallback = fallback.CoalesceRight()
		}
		return fallback.IsEmptyArrayLiteral()
	}

	return n.isShortTernary() && Node{Match: n.Child("else")}.IsEmptyArrayLiteral()
}

func (n Node) FallbackSubject() Node {
	if n.IsCoalesce() {
		return n.CoalesceLeft()
	}
	if !n.isShortTernary() {
		return Node{}
	}

	return Node{Match: n.Child("cond")}
}

func (n Node) RootVariableName() string {
	return n.inputVariable().Name()
}

func (n Node) inputVariable() engine.Match {
	at := n.Match
	for at.Kind() == "Expr_ArrayDimFetch" || isPropertyRead(at) {
		at = at.Child("var")
	}
	if at.Kind() != "Expr_Variable" || at.Name() == "this" {
		return engine.Match{}
	}

	return at
}

func (n Node) ReachesIntoParameter() bool {
	root := n.RootVariableName()
	if root == "" {
		return false
	}
	for _, param := range Params(n.EnclosingFunctionLike().Match) {
		if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() == root {
			return true
		}
	}

	return false
}

func (n Node) reachesIterationElement() bool {
	read := n.inputVariable()
	if !read.Exists() || read == n.Match {
		return false
	}
	function := n.EnclosingFunctionLike().Match
	for _, loop := range n.Ancestors() {
		if loop == function {
			break
		}
		if loop.ElementName() != read.Name() {
			continue
		}
		binding := loop.Child("valueVar")
		if !binding.EndsBefore(read) {
			continue
		}
		return !readsOwnedCollection(loop.Child("expr")) && !writesLocalBetween(function, binding, read, read.Name())
	}
	return false
}

func readsOwnedCollection(collection engine.Match) bool {
	source := collection
	if collection.Kind() == "Expr_Variable" {
		if assigned := LocalValueAt(collection); assigned.Exists() {
			source = assigned
		}
	}
	if subject := (Node{Match: source}).FallbackSubject(); subject.Exists() {
		source = subject.Match
	}
	return variableRoot(source) == "this"
}

func (n Node) IsIfElseLadder() bool {
	return n.Kind() == "Stmt_If" && len(n.ChildrenIn("elseifs")) >= 2
}

func (n Node) IsCallArgument() bool {
	return n.Exists() && n.Parent().Kind() == "Arg"
}

func (n Node) IsCallReceiver() bool {
	return n.Exists() && isMethodSend(n.Parent()) && n.Node().InRole() == "var"
}

func (n Node) IsBailOut() bool {
	switch n.Kind() {
	case "Stmt_Return", "Stmt_Continue", "Stmt_Break":
		return true
	case "Stmt_Expression":
		return n.Child("expr").Kind() == "Expr_Throw"
	}

	return false
}

func (n Node) IsShortCircuit() bool {
	return slices.Contains([]string{"Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_BooleanOr", "Expr_BinaryOp_LogicalAnd", "Expr_BinaryOp_LogicalOr"}, n.Kind())
}

func (n Node) ResultIsDiscarded() bool {
	return engine.ResultIsDiscarded(n.Match)
}

func isPropertyRead(node engine.Match) bool {
	return node.Kind() == "Expr_PropertyFetch" || node.Kind() == "Expr_NullsafePropertyFetch"
}

func isMethodSend(node engine.Match) bool {
	return node.Kind() == "Expr_MethodCall" || node.Kind() == "Expr_NullsafeMethodCall"
}
