package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// IsOptional says whether a field or parameter may be missing: written x?, or typed to admit null or undefined.
func (n Node) IsOptional() bool {
	node := n.Node()
	if node == nil {
		return false
	}

	return slices.Contains(node.Flags, "optional") || (node.Declared != nil && node.Declared.Nullable)
}

// Initializer is the value a field, variable or parameter is declared with; no node when it has none.
func (n Node) Initializer() Node {
	return Node{n.Child("initializer")}
}

// IsAbsence says whether the node is the literal null or undefined.
func (n Node) IsAbsence() bool {
	node := n.unwrap().Node()

	return node != nil && (node.Literal == "null" || node.Literal == "undefined")
}

// OwnFieldRead is the field of `this` a null-safe read defends: status in this.status?.label; empty for
// any other read.
func (n Node) OwnFieldRead() string {
	if n.Kind() != "PropertyAccessExpression" || !n.Is(engine.NullSafe) {
		return ""
	}
	receiver := Node{n.Child("expression")}.unwrap()
	if receiver.Kind() != "PropertyAccessExpression" || receiver.Child("expression").Kind() != "ThisKeyword" {
		return ""
	}

	return receiver.Name()
}

// OwnField is the field of the name the enclosing class declares; no node when it declares none.
func (n Node) OwnField(name string) Node {
	for _, member := range n.EnclosingType().ChildrenIn("members") {
		if member.Kind() == "PropertyDeclaration" && member.Name() == name {
			return Node{member}
		}
	}

	return Node{}
}
