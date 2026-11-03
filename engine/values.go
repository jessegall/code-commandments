package engine

import "github.com/jessegall/code-commandments/contract"

// LiteralKind is what kind of literal the node is, in the words every bridge writes: `string`, `int`, `float`,
// `bool`, `null`, `undefined`, `bytes`, `ellipsis`, `interpolated` or `format`. Empty for anything that is no
// literal, so a rule about a bare `true` never meets a name or a call.
func (m Match) LiteralKind() string {
	if m.node == nil {
		return ""
	}

	return m.node.Literal
}

// Value is the DECODED value of a literal its language folds, or of a static attribute: a string without its
// quotes or escapes, a boolean, null, or a number as a decimal string so no width is lost. No value for a literal
// nothing folded, and none for any other node.
func (m Match) Value() *contract.Value {
	if m.node == nil {
		return nil
	}

	return m.node.Value
}
