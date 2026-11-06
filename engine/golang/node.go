package golang

import (
	"strings"
	"unicode"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsExported() bool {
	name := n.DeclaredName()
	if name == "" {
		return false
	}
	first := []rune(name)[0]

	return unicode.IsUpper(first)
}

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

func (n Node) Symbol() string {
	if n.Node() == nil || n.Node().Facts == nil {
		return ""
	}

	return n.Node().Symbol
}

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

func isErrorAgainstNil(value, other engine.Match) bool {
	return isError(value) && other.Kind() == "Ident" && other.Name() == "nil"
}

func isError(value engine.Match) bool {
	held := value.Node()
	if held == nil || held.Resolved == nil {
		return false
	}

	return held.Resolved.Text == "error"
}
