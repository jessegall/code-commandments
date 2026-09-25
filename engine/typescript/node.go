// Package typescript is the engine's view of TypeScript: a module, a <script> block or a template expression,
// read from the generic tree the frontend bridge writes in the compiler's own vocabulary.
package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// Node is a TypeScript node.
type Node struct {
	engine.Match
}

// Decorate views a match as a TypeScript node.
func (Node) Decorate(m engine.Match) Node {
	return Node{m}
}

// Of views a match as a TypeScript node.
func Of(m engine.Match) Node {
	return Node{m}
}

// Operator is the operator of a binary, unary or assignment expression; empty on any other node.
func (n Node) Operator() string {
	if n.Node() == nil {
		return ""
	}

	return n.Node().Operator
}

// IsEquality says whether the node is an equality comparison, strict or loose: ===, ==.
func (n Node) IsEquality() bool {
	return n.Kind() == "BinaryExpression" && slices.Contains([]string{"===", "=="}, n.Operator())
}

// Left is a binary expression's left operand.
func (n Node) Left() Node {
	return Node{n.Child("left")}
}

// Right is a binary expression's right operand.
func (n Node) Right() Node {
	return Node{n.Child("right")}
}

// IsLiteral says whether the node is a literal with a value of its own: a string, number, boolean or null,
// never a template with holes.
func (n Node) IsLiteral() bool {
	node := n.Node()

	return node != nil && node.Literal != "" && node.Literal != "interpolated"
}

// IsReference says whether the node names a value to read: a name, a member a.b or a?.b, or an index a[b].
func (n Node) IsReference() bool {
	return slices.Contains([]string{"Identifier", "PropertyAccessExpression", "ElementAccessExpression"}, n.Kind())
}

// Source is the node's source text as written.
func (n Node) Source() string {
	span, err := n.Span()
	if err != nil {
		return ""
	}

	return span.Text()
}

// LiteralValue is a literal's value as its source decodes it: `paid` for 'paid', `1` for 1.
func (n Node) LiteralValue() string {
	node := n.Node()
	if node == nil || node.Value == nil {
		return n.Source()
	}
	if text, ok := node.Value.Text(); ok {
		return text
	}

	return n.Source()
}
