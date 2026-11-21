package typescript

import (
	"slices"

)

func (n Node) IsOptional() bool {
	node := n.Node()
	if node == nil {
		return false
	}

	return slices.Contains(node.Modifiers, "optional") || node.Written.IsNullable()
}

func (n Node) IsAbsence() bool {
	node := n.unwrap().Node()

	return node != nil && (node.LiteralKind == "null" || node.LiteralKind == "undefined")
}

