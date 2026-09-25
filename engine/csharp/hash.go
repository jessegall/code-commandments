package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// dataLiterals are the literal kinds that hold data rather than meaning.
var dataLiterals = []string{"StringLiteralExpression", "Utf8StringLiteralExpression", "NumericLiteralExpression", "CharacterLiteralExpression", "InterpolatedStringText"}

// hashRules is how C# code is fingerprinted. A local name is an identifier outside a type position: the type a
// body creates or casts to is what it does, so it survives normalising, and so does the member an access reads. A
// string, number or character is data and blanks; `true`, `null` and `default` carry meaning and stay. Attributes
// decorate code without running, so they never count, and braces around a single statement are layout.
type hashRules struct{}

var hashing engine.HashRules = hashRules{}

func (hashRules) Counts(node engine.Match) bool {
	return node.Kind() != "AttributeList"
}

// Weight counts every expression and every node outside one, as the PHP engine weighs a C# subtree: the argument
// lists and statements inside an expression are its parts, not nodes of their own.
func (hashRules) Weight(node engine.Match) int {
	if (Node{node}).IsExpression() || !insideAnExpression(node) {
		return 1
	}

	return 0
}

// insideAnExpression says whether an expression holds the node: a lambda's parameters and statements, an argument
// list, a deconstruction's designations.
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

// isAccessedMember says whether the name is the member an access reads: `Total` in `order.Total`.
func isAccessedMember(node engine.Match) bool {
	return node.Node().Field == "Name" && slices.Contains([]string{"SimpleMemberAccessExpression", "PointerMemberAccessExpression", "MemberBindingExpression"}, node.Parent().Kind())
}

// Declares says whether the node declares a name normalising leaves out: a declaration or statement outside any
// expression. A part of an expression keeps its name, as the PHP engine reads it there.
func (hashRules) Declares(node engine.Match) bool {
	return !(Node{node}).IsExpression() && node.Node().Role != "type" && !insideAnExpression(node)
}

func (hashRules) IsCallee(node engine.Match) bool {
	return node.Node().Field == "Expression" && node.Parent().Kind() == "InvocationExpression"
}

// Leaf reads the member an access reads by its name alone, its type arguments aside: `Load<Order>()` and
// `Load<Item>()` are one call waiting for a type parameter.
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

// Unwrap reads braces around a single statement as the statement.
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

// ExpressionHash is a formatting-blind fingerprint of the expression, names and literals as written.
func ExpressionHash(n Node) string {
	return engine.SyntaxHash([]engine.Match{n.Match}, hashing, false)
}
