package golang

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// dataLiterals are the literal kinds that hold data rather than meaning. Go writes `true`, `false` and `nil` as
// identifiers rather than literals, so none of them is here: what they mean is the type checker's to say.
var dataLiterals = []string{"string", "int", "float", "imag", "char"}

// hashRules is how Go code is fingerprinted. A local name is an identifier outside a type position: the type a
// body creates or converts to is what it does, so it survives normalising, and so does the member a selector
// reads. A string, a number or a rune is data and blanks. Braces around a single statement are layout.
type hashRules struct{}

var hashing engine.HashRules = hashRules{}

// Counts says whether the node counts: every Go node does, since Go writes nothing that decorates without
// running.
func (hashRules) Counts(engine.Match) bool {
	return true
}

// Weight counts every expression and every node outside one, as the other engines weigh a subtree: the arguments
// and the statements inside an expression are its parts, not nodes of their own.
func (hashRules) Weight(node engine.Match) int {
	if node.Role() == "expression" || !insideAnExpression(node) {
		return 1
	}

	return 0
}

// insideAnExpression says whether an expression holds the node: a function literal's statements, a call's
// arguments, a composite literal's elements.
func insideAnExpression(node engine.Match) bool {
	for around := node.Parent(); around.Exists(); around = around.Parent() {
		if around.Role() == "expression" {
			return true
		}
	}

	return false
}

// IsName says whether the node is a local name: an identifier that is neither a written type nor the member a
// selector reads, since renaming a local changes nothing and calling another member does.
func (hashRules) IsName(node engine.Match) bool {
	return node.Kind() == "Ident" && node.Role() != "type" && !isSelectedMember(node)
}

// isSelectedMember says whether the name is the member a selector reads: `Total` in `order.Total`.
func isSelectedMember(node engine.Match) bool {
	return node.Field() == "sel" && node.Parent().Kind() == "SelectorExpr"
}

// Declares says whether the node carries a name normalising leaves out: a declaration or a statement outside any
// expression. A part of an expression keeps its name.
func (hashRules) Declares(node engine.Match) bool {
	return node.Role() != "expression" && !insideAnExpression(node)
}

// IsCallee says whether the node is what a call calls, whose name normalising keeps.
func (hashRules) IsCallee(node engine.Match) bool {
	return node.Field() == "fun" && node.Parent().Kind() == "CallExpr"
}

// Leaf reads the member a selector reads by its name alone.
func (hashRules) Leaf(node engine.Match) (string, bool) {
	if !isSelectedMember(node) {
		return "", false
	}

	return "member:" + node.Name(), true
}

// Literal is how a literal reads, its datum blanked when normalising: two bodies that differ only in which string
// they hold are one body.
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

// Unwrap reads braces around a single statement as the statement, since `if x { return }` and a block holding one
// return are the same code.
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

// BodyHash is the fingerprint of a function's own statements, names and literals as written; empty for anything
// that is no function.
func BodyHash(function engine.Match) string {
	if !function.Is(engine.Function) {
		return ""
	}

	return engine.SyntaxHash(function.Body(), hashing, false)
}

// NodeHash is the fingerprint of one node, normalised or as written: what the repeat checks read a node by.
func NodeHash(node engine.Match, normalize bool) string {
	return engine.SyntaxHash([]engine.Match{node}, hashing, normalize)
}
