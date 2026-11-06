package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsOptional() bool {
	node := n.Node()
	if node == nil {
		return false
	}

	return slices.Contains(node.Flags, "optional") || (node.Declared != nil && node.Declared.Nullable)
}

func (n Node) Initializer() Node {
	return Node{n.Child("initializer")}
}

func (n Node) IsAbsence() bool {
	node := n.unwrap().Node()

	return node != nil && (node.Literal == "null" || node.Literal == "undefined")
}

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

func (n Node) OwnField(name string) Node {
	for _, member := range n.EnclosingType().ChildrenIn("members") {
		if member.Kind() == "PropertyDeclaration" && member.Name() == name {
			return Node{member}
		}
	}

	return Node{}
}
