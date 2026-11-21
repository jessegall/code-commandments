package typescript

import (

	"github.com/jessegall/code-commandments/contract"
)

func (n Node) Declared() *contract.Type {
	if n.Node() == nil {
		return nil
	}

	return n.Node().Written
}

func (n Node) TypeName() string {
	if resolved := n.Resolved(); resolved != nil && resolved.Name != "" {
		return resolved.Name
	}

	if inferred, told := n.InferType(); told && inferred != nil {
		return inferred.Render()
	}

	if declared := n.Declared(); declared != nil {
		return declared.Name
	}

	return ""
}

func (n Node) Resolved() *contract.Type {
	if n.Node() == nil {
		return nil
	}

	return n.Node().Resolved
}
