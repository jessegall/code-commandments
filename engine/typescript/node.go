// Package typescript is the engine's view of TypeScript: a module, a <script> block or a template expression, read from the generic tree the frontend bridge writes in the compiler's own vocabulary.
package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// Node is a TypeScript node.
type Node struct {
	engine.Match
}

func (Node) Decorate(m engine.Match) Node {
	return Node{m}
}

func Of(m engine.Match) Node {
	return Node{m}
}

func (n Node) Operator() string {
	return n.Match.Operator()
}

func (n Node) IsEquality() bool {
	return n.Kind() == "BinaryExpression" && slices.Contains([]string{"===", "=="}, n.Operator())
}

func (n Node) Left() Node {
	return Node{n.Child("left")}
}

func (n Node) Right() Node {
	return Node{n.Child("right")}
}

func (n Node) IsLiteral() bool {
	node := n.Node()

	return node != nil && node.Literal != "" && node.Literal != "interpolated"
}

func (n Node) IsReference() bool {
	return slices.Contains([]string{"Identifier", "PropertyAccessExpression", "ElementAccessExpression"}, n.Kind())
}

func (n Node) Source() string {
	span, err := n.Span()
	if err != nil {
		return ""
	}

	return span.Text()
}

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
