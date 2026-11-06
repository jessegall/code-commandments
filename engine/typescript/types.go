package typescript

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

func IsArray(t *contract.Type) bool {
	if t == nil {
		return false
	}
	if t.Kind == "array" || t.Kind == "tuple" {
		return true
	}

	return t.Kind == "named" && (t.Name == "Array" || t.Name == "ReadonlyArray")
}

func ElementOf(t *contract.Type) *contract.Type {
	if !IsArray(t) || t.Kind == "tuple" {
		return nil
	}
	if t.Element != nil {
		return t.Element
	}
	if len(t.Args) == 1 {
		return t.Args[0]
	}

	return nil
}

func IsPrimitive(t *contract.Type) bool {
	if t == nil {
		return false
	}
	switch t.Kind {
	case "keyword":
		return t.Name == "string" || t.Name == "number" || t.Name == "boolean" || t.Name == "bigint"
	case "literal":
		return true
	case "union":
		for _, member := range t.Members {
			if !IsPrimitive(member) {
				return false
			}
		}

		return len(t.Members) > 0
	}

	return false
}

func DeclaredName(symbol string) string {
	return symbol[strings.LastIndex(symbol, "#")+1:]
}

func (n Node) Declared() *contract.Type {
	if n.Node() == nil {
		return nil
	}

	return n.Node().Declared
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
