package typescript

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// IsArray says whether the type is an array or a tuple: T[], Array<T>, readonly T[], [A, B].
func IsArray(t *contract.Type) bool {
	if t == nil {
		return false
	}
	if t.Kind == "array" || t.Kind == "tuple" {
		return true
	}

	return t.Kind == "named" && (t.Name == "Array" || t.Name == "ReadonlyArray")
}

// ElementOf is the type an array holds: its element, or its one type argument; nil for anything that is no array.
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

// IsPrimitive says whether the type is a string, a number, a boolean or a bigint, or a literal or a union of them: a
// value with no identity besides itself.
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

// DeclaredName is a symbol id's declared name, the part after its path: Ref for .../reactivity.d.ts#Ref.
func DeclaredName(symbol string) string {
	return symbol[strings.LastIndex(symbol, "#")+1:]
}

// Declared is the type the source writes on a declaration; nil when it writes none.
func (n Node) Declared() *contract.Type {
	if n.Node() == nil {
		return nil
	}

	return n.Node().Declared
}

// Resolved is the type the checker gives an expression; nil where it gave none.
func (n Node) Resolved() *contract.Type {
	if n.Node() == nil {
		return nil
	}

	return n.Node().Resolved
}
