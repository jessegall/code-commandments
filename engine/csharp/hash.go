package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var dataLiterals = []string{"StringLiteralExpression", "Utf8StringLiteralExpression", "NumericLiteralExpression", "CharacterLiteralExpression", "InterpolatedStringText"}

// hashRules is how C# code is fingerprinted.
type hashRules struct{}

var hashing engine.HashRules = hashRules{}

func (hashRules) Counts(node engine.Match) bool {
	return node.Kind() != "AttributeList"
}

func (hashRules) Weight(node engine.Match) int {
	if (Node{node}).IsExpression() || !insideAnExpression(node) {
		return 1
	}

	return 0
}

func insideAnExpression(node engine.Match) bool {
	for around := node.Parent(); around.Exists(); around = around.Parent() {
		if (Node{around}).IsExpression() {
			return true
		}
	}

	return false
}

func (hashRules) IsName(node engine.Match) bool {
	return node.Kind() == "IdentifierName" && node.Node().Role != "type" && !isAccessedMember(node)
}

func isAccessedMember(node engine.Match) bool {
	return node.Node().Field == "Name" && slices.Contains([]string{"SimpleMemberAccessExpression", "PointerMemberAccessExpression", "MemberBindingExpression"}, node.Parent().Kind())
}

func (hashRules) Declares(node engine.Match) bool {
	return !(Node{node}).IsExpression() && !insideAnExpression(node)
}

func (hashRules) IsCallee(node engine.Match) bool {
	return node.Node().Field == "Expression" && node.Parent().Kind() == "InvocationExpression"
}

func (hashRules) Leaf(node engine.Match) (string, bool) {
	if !isAccessedMember(node) {
		return "", false
	}

	return "member:" + node.Name(), true
}

func (hashRules) Literal(node engine.Match, normalize bool) (string, bool) {
	literal := Node{node}
	if !literal.IsLiteral() && !literal.Is("InterpolatedStringText") {
		return "", false
	}
	if normalize && slices.Contains(dataLiterals, node.Kind()) {
		return "lit:" + node.Kind() + ":_", true
	}

	return "lit:" + node.Kind() + ":" + literal.Text(), true
}

func (hashRules) Unwrap(node engine.Match) (engine.Match, bool) {
	if node.Kind() != "Block" {
		return node, false
	}
	var counted []engine.Match
	for _, child := range node.Children() {
		if !(Node{child}).IsExpression() && child.Kind() != "AttributeList" {
			counted = append(counted, child)
		}
	}
	if len(counted) != 1 {
		return node, false
	}

	return counted[0], true
}

func ExpressionHash(n Node) string {
	return engine.SyntaxHash([]engine.Match{n.Match}, hashing, false)
}
