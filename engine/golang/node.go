package golang

import (
	"strings"
	"unicode"

	"github.com/jessegall/code-commandments/engine"
)

// IsExported says whether what the node declares can be read outside its package, which in Go is the first
// letter of its name and nothing else: there is no `public` or `private` keyword to read, so a reader that asked
// for a modifier would find none and answer wrongly for every declaration in the language.
func (n Node) IsExported() bool {
	name := n.DeclaredName()
	if name == "" {
		return false
	}
	first := []rune(name)[0]

	return unicode.IsUpper(first)
}

// OwnerType is the type declaration a method belongs to; no node for a function that belongs to none.
//
// Go declares a method outside its type, so nothing above a method says what it belongs to: `EnclosingType`,
// which every language answers by nesting, answers nothing here and is right to. What a method belongs to is the
// type its receiver names, and the two are joined by the id each carries: a method
// `example.com/shop/cart.Cart.Total` belongs to `example.com/shop/cart.Cart`.
func (n Node) OwnerType() engine.Match {
	id := n.OwnerSymbol()
	if id == "" {
		return engine.Match{}
	}
	for _, declaration := range n.Codebase().Declarations(id) {
		return declaration
	}

	return engine.Match{}
}

// OwnerSymbol is the id of the type a method belongs to, read off the method's own id; empty for anything that
// belongs to no type.
func (n Node) OwnerSymbol() string {
	if n.Kind() != "FuncDecl" || !n.Child("recv").Exists() {
		return ""
	}
	id := n.Symbol()
	at := strings.LastIndex(id, ".")
	if at < 0 {
		return ""
	}

	return id[:at]
}

// Symbol is the id of the declaration the node is, as the bridge wrote it.
func (n Node) Symbol() string {
	if n.Node() == nil || n.Node().Facts == nil {
		return ""
	}

	return n.Node().Symbol
}

// IsErrorCheck says whether the node is Go's own guard, `if err != nil`: a comparison of a value whose type is
// `error` against nil.
//
// It is read through the type the checker gave, never through the name `err`: a project is free to call it
// anything, and a value named `err` that is not an error is not this shape. It is the pardon half of a dozen
// rules need, since the guard is the most repeated condition in the language and no rule about repeated
// conditions or nesting may count it.
func (n Node) IsErrorCheck() bool {
	condition := n.Match
	if n.Kind() == "IfStmt" {
		condition = n.Child("cond")
	}
	if condition.Kind() != "BinaryExpr" || (condition.Operator() != "!=" && condition.Operator() != "==") {
		return false
	}

	left, right := condition.Child("x"), condition.Child("y")

	return isErrorAgainstNil(left, right) || isErrorAgainstNil(right, left)
}

// isErrorAgainstNil says whether the value's type is error and the other side is nil.
func isErrorAgainstNil(value, other engine.Match) bool {
	return isError(value) && other.Kind() == "Ident" && other.Name() == "nil"
}

// isError says whether the expression's type is the error the language itself declares.
func isError(value engine.Match) bool {
	held := value.Node()
	if held == nil || held.Resolved == nil {
		return false
	}

	return held.Resolved.Text == "error"
}
