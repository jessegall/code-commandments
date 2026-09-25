package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// IsFunctionLike says whether the node opens a function scope: a method, a function, a closure, an arrow function or
// a property hook.
func (n Node) IsFunctionLike() bool {
	return slices.Contains(functionLikes, n.Kind())
}

// EnclosingFunctionLike is the node itself when it opens a function scope, else the nearest one around it.
func (n Node) EnclosingFunctionLike() Node {
	for at := n; at.Exists(); at = at.Up() {
		if at.IsFunctionLike() {
			return at
		}
	}

	return Node{}
}

// IsLoopSubject says whether the node is the collection a foreach walks.
func (n Node) IsLoopSubject() bool {
	return n.Exists() && n.Parent().Kind() == "Stmt_Foreach" && n.Node().Field == "expr"
}

// IsCoalesce says whether the node is a ?? expression.
func (n Node) IsCoalesce() bool {
	return n.Kind() == "Expr_BinaryOp_Coalesce"
}

// CoalesceLeft is the left side of a ?? expression; no node otherwise.
func (n Node) CoalesceLeft() Node {
	if !n.IsCoalesce() {
		return Node{}
	}

	return Node{Match: n.Child("left")}
}

// CoalesceRight is the right side of a ?? expression; no node otherwise.
func (n Node) CoalesceRight() Node {
	if !n.IsCoalesce() {
		return Node{}
	}

	return Node{Match: n.Child("right")}
}

// IsTernary says whether the node is a ternary, the short ?: included.
func (n Node) IsTernary() bool {
	return n.Kind() == "Expr_Ternary"
}

// isShortTernary says whether the node is a ?: ternary, which has no middle branch.
func (n Node) isShortTernary() bool {
	return n.IsTernary() && !n.Child("if").Exists()
}

// FallsBackToEmptyCollection says whether the node is a ?? or ?: whose fallback is an empty array.
func (n Node) FallsBackToEmptyCollection() bool {
	if n.IsCoalesce() {
		return n.CoalesceRight().IsEmptyArrayLiteral()
	}

	return n.isShortTernary() && Node{Match: n.Child("else")}.IsEmptyArrayLiteral()
}

// FallbackSubject is the value a ?? or ?: falls back from; no node otherwise.
func (n Node) FallbackSubject() Node {
	if n.IsCoalesce() {
		return n.CoalesceLeft()
	}
	if !n.isShortTernary() {
		return Node{}
	}

	return Node{Match: n.Child("cond")}
}

// RootVariableName is the variable a chain of property and offset reads starts from; empty when it starts
// elsewhere, at $this, or at a variable variable.
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

// ReachesIntoParameter says whether the node reads from a parameter of the function around it.
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

// IsDeeplyNestedIf says whether the node is an if sitting inside two or more ifs of its own function.
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

// IsIfElseLadder says whether the node is an if with two or more elseifs.
func (n Node) IsIfElseLadder() bool {
	return n.Kind() == "Stmt_If" && len(n.In("elseifs")) >= 2
}

// IsCallArgument says whether the node is the value an argument passes.
func (n Node) IsCallArgument() bool {
	return n.Exists() && n.Parent().Kind() == "Arg"
}

// IsCallReceiver says whether the node is the object a method is sent to.
func (n Node) IsCallReceiver() bool {
	return n.Exists() && isMethodSend(n.Parent()) && n.Node().Field == "var"
}

// IsSoleLoopBodyGuard says whether the node is an if with no else that is the only statement of a loop's body and
// guards two or more statements of its own.
func (n Node) IsSoleLoopBodyGuard() bool {
	if n.Kind() != "Stmt_If" || n.Child("else").Exists() || len(n.In("elseifs")) > 0 {
		return false
	}
	loop := n.Up()
	if !slices.Contains([]string{"Stmt_Foreach", "Stmt_For", "Stmt_While"}, loop.Kind()) {
		return false
	}
	body := loop.In("stmts")

	return len(body) == 1 && body[0].Node() == n.Node() && len(n.In("stmts")) >= 2
}

// IsOutermostNestedTernary says whether the node is a ternary with a ternary in a branch, and no ternary around it
// in its function.
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

// counterSteps are the step expressions that advance a counter.
var counterSteps = []string{"Expr_PostInc", "Expr_PreInc", "Expr_PostDec", "Expr_PreDec", "Expr_AssignOp_Plus", "Expr_AssignOp_Minus"}

// IsNonCountingFor says whether the node is a for loop with steps, none of which advances a counter.
func (n Node) IsNonCountingFor() bool {
	steps := n.In("loop")
	if n.Kind() != "Stmt_For" || len(steps) == 0 {
		return false
	}

	return !slices.ContainsFunc(steps, func(step engine.Match) bool { return slices.Contains(counterSteps, step.Kind()) })
}

// HasRedundantElse says whether the node is an if with an else and no elseif whose own body ends by leaving.
func (n Node) HasRedundantElse() bool {
	if n.Kind() != "Stmt_If" || !n.Child("else").Exists() || len(n.In("elseifs")) > 0 {
		return false
	}
	body := n.In("stmts")

	return len(body) > 0 && Node{Match: body[len(body)-1]}.IsBailOut()
}

// IsBailOut says whether the node is a statement that leaves: return, continue, break or a thrown expression.
func (n Node) IsBailOut() bool {
	switch n.Kind() {
	case "Stmt_Return", "Stmt_Continue", "Stmt_Break":
		return true
	case "Stmt_Expression":
		return n.Child("expr").Kind() == "Expr_Throw"
	}

	return false
}

// IsShortCircuit says whether the node is a boolean and or or, in either spelling.
func (n Node) IsShortCircuit() bool {
	return slices.Contains([]string{"Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_BooleanOr", "Expr_BinaryOp_LogicalAnd", "Expr_BinaryOp_LogicalOr"}, n.Kind())
}

// ResultIsDiscarded says whether the node is an expression statement's whole expression, its value thrown away.
func (n Node) ResultIsDiscarded() bool {
	return n.Exists() && n.Parent().Kind() == "Stmt_Expression" && n.Node().Field == "expr"
}

// In is every child the node holds in one field, in order: an if's elseifs, a loop's statements.
func (n Node) In(field string) []engine.Match {
	var held []engine.Match
	for _, child := range n.Children() {
		if child.Node().Field == field {
			held = append(held, child)
		}
	}

	return held
}

func isPropertyRead(node engine.Match) bool {
	return node.Kind() == "Expr_PropertyFetch" || node.Kind() == "Expr_NullsafePropertyFetch"
}

func isMethodSend(node engine.Match) bool {
	return node.Kind() == "Expr_MethodCall" || node.Kind() == "Expr_NullsafeMethodCall"
}
