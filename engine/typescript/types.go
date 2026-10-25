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

// TypeName is the type the expression has: what the checker gave it, else what the engine infers from its shape,
// else the type the source writes for a declaration.
//
// The checker is asked first because it answers what nothing else can: `price.amount` is a `number` and
// `label.amount` a `string` without a line of inference here, which is the difference between an amount and a
// label a rule about money has to see.
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

// Resolved is the type the checker gives an expression; nil where it gave none.
func (n Node) Resolved() *contract.Type {
	if n.Node() == nil {
		return nil
	}

	return n.Node().Resolved
}
