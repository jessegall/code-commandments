package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func IsOperand(node engine.Match) bool {
	switch node.Kind() {
	case "Expr_Variable":
		return node.Name() != ""
	case "Scalar_String", "Scalar_Int", "Scalar_Float", "Expr_ClassConstFetch":
		return true
	}

	return false
}

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

func RoutesThroughSelf(expr engine.Match) bool {
	for _, node := range withDescendants(expr) {
		class := node.Child("class")
		if node.Kind() == "Expr_StaticCall" && isName(class) && (strings.EqualFold(class.Name(), "self") || strings.EqualFold(class.Name(), "static")) {
			return true
		}
	}

	return false
}

func NamesAClass(node engine.Match) bool {
	name := node.Child("name")

	return node.Kind() == "Expr_ClassConstFetch" && name.Kind() == "Identifier" && strings.EqualFold(name.Name(), "class")
}

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
