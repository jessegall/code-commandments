package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var stateComparisons = []string{
	"Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical", "Expr_BinaryOp_Equal", "Expr_BinaryOp_NotEqual",
}

func (n Node) GuardsAheadOfItsTransition() bool {
	if n.Kind() != "Stmt_If" || len(n.ChildrenIn("elseifs")) > 0 || n.Child("else").Exists() || !n.onlyThrows() {
		return false
	}
	receiver := n.stateTestedReceiver()

	return receiver != "" && n.receiverIsToldAfterwards(receiver)
}

func (n Node) onlyThrows() bool {
	body := n.ChildrenIn("stmts")

	return len(body) == 1 && body[0].Kind() == "Stmt_Expression" && body[0].Child("expr").Kind() == "Expr_Throw"
}

func (n Node) stateTestedReceiver() string {
	receiver := ""
	for _, comparison := range flattenLogic(n.Child("cond")) {
		tested := n.enumComparedReceiver(comparison)
		if tested == "" || (receiver != "" && tested != receiver) {
			return ""
		}
		receiver = tested
	}

	return receiver
}

func flattenLogic(node engine.Match) []engine.Match {
	if node.Kind() == "Expr_BinaryOp_BooleanAnd" || node.Kind() == "Expr_BinaryOp_BooleanOr" {
		return append(flattenLogic(node.Child("left")), flattenLogic(node.Child("right"))...)
	}

	return []engine.Match{node}
}

func (n Node) enumComparedReceiver(comparison engine.Match) string {
	if !slices.Contains(stateComparisons, comparison.Kind()) {
		return ""
	}
	left, right := comparison.Child("left"), comparison.Child("right")
	if n.isEnumCase(left) {
		left, right = right, left
	}
	if !n.isEnumCase(right) || left.Kind() != "Expr_PropertyFetch" {
		return ""
	}
	if owner := variableName(left.Child("var")); owner != "this" {
		return owner
	}

	return ""
}

func (n Node) isEnumCase(node engine.Match) bool {
	class := node.Child("class")

	return node.Kind() == "Expr_ClassConstFetch" && isName(class) && ProgramOf(n.Codebase()).IsEnum(namedClass(node, class))
}

func (n Node) receiverIsToldAfterwards(receiver string) bool {
	end := n.Node().Span.End
	for _, sibling := range n.Parent().ChildrenIn(n.Node().Field) {
		if sibling.Node().Span.Start < end || (sibling.Kind() != "Stmt_Expression" && sibling.Kind() != "Stmt_Return") {
			continue
		}
		if told := sibling.Child("expr"); told.Kind() == "Expr_MethodCall" && variableName(told.Child("var")) == receiver {
			return true
		}
	}

	return false
}
