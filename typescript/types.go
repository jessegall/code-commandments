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
