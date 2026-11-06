package golang

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var dataLiterals = []string{"string", "int", "float", "imag", "char"}

// hashRules is how Go code is fingerprinted.
type hashRules struct{}

var hashing engine.HashRules = hashRules{}

func (hashRules) Counts(engine.Match) bool {
	return true
}

func (hashRules) Weight(node engine.Match) int {
	if node.Role() == "expression" || !insideAnExpression(node) {
		return 1
	}

	return 0
}

func insideAnExpression(node engine.Match) bool {
	for around := node.Parent(); around.Exists(); around = around.Parent() {
		if around.Role() == "expression" {
			return true
		}
	}

	return false
}

func (hashRules) IsName(node engine.Match) bool {
	return node.Kind() == "Ident" && node.Role() != "type" && !isSelectedMember(node)
}

func isSelectedMember(node engine.Match) bool {
	return node.Field() == "sel" && node.Parent().Kind() == "SelectorExpr"
}

func (hashRules) Declares(node engine.Match) bool {
	return node.Role() != "expression" && !insideAnExpression(node)
}

func (hashRules) IsCallee(node engine.Match) bool {
	return node.Field() == "fun" && node.Parent().Kind() == "CallExpr"
}

func (hashRules) Leaf(node engine.Match) (string, bool) {
	if !isSelectedMember(node) {
		return "", false
	}

	return "member:" + node.Name(), true
}

func (hashRules) Literal(node engine.Match, normalize bool) (string, bool) {
	kind := node.LiteralKind()
	if kind == "" {
		return "", false
	}

	if normalize && slices.Contains(dataLiterals, kind) {
		return "lit:" + kind + ":_", true
	}

	return "lit:" + kind + ":" + node.Written(), true
}

func (hashRules) Unwrap(node engine.Match) (engine.Match, bool) {
	if node.Kind() != "BlockStmt" {
		return node, false
	}

	statements := node.Children()
	if len(statements) != 1 {
		return node, false
	}

	return statements[0], true
}

func BodyHash(function engine.Match) string {
	if !function.Is(engine.Function) {
		return ""
	}

	return engine.SyntaxHash(function.Body(), hashing, false)
}

func NodeHash(node engine.Match, normalize bool) string {
	return engine.SyntaxHash([]engine.Match{node}, hashing, normalize)
}
