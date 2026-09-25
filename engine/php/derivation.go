package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// IsOperand says whether the node is a leaf a derivation reads: a named variable, a string or number, or a class
// constant.
func IsOperand(node engine.Match) bool {
	switch node.Kind() {
	case "Expr_Variable":
		return node.Name() != ""
	case "Scalar_String", "Scalar_Int", "Scalar_Float", "Expr_ClassConstFetch":
		return true
	}

	return false
}

// OperandsIn is how many operands the expression reads.
func OperandsIn(expr engine.Match) int {
	if IsOperand(expr) {
		return 1
	}
	operands := 0
	for _, child := range expr.Children() {
		operands += OperandsIn(child)
	}

	return operands
}

// IsReadOfSomething says whether the expression, casts aside, reads a property or calls something.
func IsReadOfSomething(expr engine.Match) bool {
	for strings.HasPrefix(expr.Kind(), "Expr_Cast_") {
		expr = expr.Child("expr")
	}
	switch expr.Kind() {
	case "Expr_PropertyFetch", "Expr_NullsafePropertyFetch", "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall", "Expr_FuncCall":
		return true
	}

	return false
}

// RoutesThroughSelf says whether a static call on self or static sits anywhere in the expression.
func RoutesThroughSelf(expr engine.Match) bool {
	for _, node := range withDescendants(expr) {
		class := node.Child("class")
		if node.Kind() == "Expr_StaticCall" && isName(class) && (strings.EqualFold(class.Name(), "self") || strings.EqualFold(class.Name(), "static")) {
			return true
		}
	}

	return false
}

// NamesAClass says whether the node is a ::class fetch.
func NamesAClass(node engine.Match) bool {
	name := node.Child("name")

	return node.Kind() == "Expr_ClassConstFetch" && name.Kind() == "Identifier" && strings.EqualFold(name.Name(), "class")
}

// SoleOperandIn is the one operand the expression reads; no node when it reads none or several.
func SoleOperandIn(expr engine.Match) engine.Match {
	if IsOperand(expr) {
		return expr
	}
	var found engine.Match
	count := 0
	for _, child := range expr.Children() {
		sole := SoleOperandIn(child)
		under := 1
		if !sole.Exists() {
			under = OperandsIn(child)
		}
		for range under {
			count++
			if count > 1 {
				return engine.Match{}
			}
			found = sole
			if !sole.Exists() {
				found = child
			}
		}
	}

	return found
}
