package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// selfContained is every expression kind that reads as one unit, so a leading `!` cannot swallow part of it.
var selfContained = map[string]bool{
	"Expr_Variable": true, "Expr_PropertyFetch": true, "Expr_NullsafePropertyFetch": true, "Expr_StaticPropertyFetch": true,
	"Expr_ArrayDimFetch": true, "Expr_FuncCall": true, "Expr_MethodCall": true, "Expr_NullsafeMethodCall": true,
	"Expr_StaticCall": true, "Expr_ConstFetch": true, "Expr_ClassConstFetch": true, "Expr_Isset": true, "Expr_Empty": true,
}

// inverse maps each equality operator to its exact inverse: the one flip that needs no `!`, true for every value.
var inverse = map[string]string{
	"Expr_BinaryOp_Identical":    "!==",
	"Expr_BinaryOp_NotIdentical": "===",
	"Expr_BinaryOp_Equal":        "!=",
	"Expr_BinaryOp_NotEqual":     "==",
}

// Negation is the source of a condition with its truth flipped, ready to drop into an `if (…)`: a `!` taken off, an
// equality inverted, a `!` put in front, parenthesised only where the condition is not one unit.
func Negation(condition engine.Match) string {
	if condition.Kind() == "Expr_BooleanNot" {
		return sourceOf(condition.Child("expr"))
	}
	if operator, inverts := inverse[condition.Kind()]; inverts {
		return sourceOf(condition.Child("left")) + " " + operator + " " + sourceOf(condition.Child("right"))
	}
	if selfContained[condition.Kind()] || strings.HasPrefix(condition.Kind(), "Scalar_") {
		return "! " + sourceOf(condition)
	}

	return "! (" + sourceOf(condition) + ")"
}

// sourceOf is the source a node spans, verbatim.
func sourceOf(node engine.Match) string {
	span, err := node.Span()
	if err != nil {
		return ""
	}

	return span.Text()
}
