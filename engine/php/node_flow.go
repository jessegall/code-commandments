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
	return n.Exists() && n.Parent().Kind() == "Stmt_Foreach" && n.Node().Field == "expr"
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
		return n.CoalesceRight().IsEmptyArrayLiteral()
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
	at := n.Match
	for at.Kind() == "Expr_ArrayDimFetch" || isPropertyRead(at) {
		at = at.Child("var")
	}
	if at.Kind() != "Expr_Variable" || at.Name() == "this" {
		return ""
	}

	return at.Name()
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

func (n Node) IsDeeplyNestedIf() bool {
	if n.Kind() != "Stmt_If" {
		return false
	}
	depth := 0
	for at := n.Up(); at.Exists() && !at.IsFunctionLike(); at = at.Up() {
		if at.Kind() == "Stmt_If" {
			depth++
		}
	}

	return depth >= 2
}

func (n Node) IsIfElseLadder() bool {
	return n.Kind() == "Stmt_If" && len(n.ChildrenIn("elseifs")) >= 2
}

func (n Node) IsCallArgument() bool {
	return n.Exists() && n.Parent().Kind() == "Arg"
}

func (n Node) IsCallReceiver() bool {
	return n.Exists() && isMethodSend(n.Parent()) && n.Node().Field == "var"
}

func (n Node) IsOutermostNestedTernary() bool {
	if !n.IsTernary() {
		return false
	}
	for at := n.Up(); at.Exists() && !at.IsFunctionLike(); at = at.Up() {
		if at.IsTernary() {
			return false
		}
	}
	for _, branch := range []engine.Match{n.Child("if"), n.Child("else")} {
		if branch.Exists() && slices.ContainsFunc(append([]engine.Match{branch}, descendantsOf(branch)...), func(m engine.Match) bool {
			return m.Kind() == "Expr_Ternary"
		}) {
			return true
		}
	}

	return false
}

var counterSteps = []string{"Expr_PostInc", "Expr_PreInc", "Expr_PostDec", "Expr_PreDec", "Expr_AssignOp_Plus", "Expr_AssignOp_Minus"}

func (n Node) IsNonCountingFor() bool {
	steps := n.ChildrenIn("loop")
	if n.Kind() != "Stmt_For" || len(steps) == 0 {
		return false
	}

	return !slices.ContainsFunc(steps, func(step engine.Match) bool { return slices.Contains(counterSteps, step.Kind()) })
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
	return n.Exists() && n.Parent().Kind() == "Stmt_Expression" && n.Node().Field == "expr"
}

func isPropertyRead(node engine.Match) bool {
	return node.Kind() == "Expr_PropertyFetch" || node.Kind() == "Expr_NullsafePropertyFetch"
}

func isMethodSend(node engine.Match) bool {
	return node.Kind() == "Expr_MethodCall" || node.Kind() == "Expr_NullsafeMethodCall"
}
