// Package phptypes is what the engine knows of jessegall/php-types, stated once.
package phptypes

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const optionName = "Option"

// Node is a match read in jessegall/php-types' terms.
type Node struct {
	engine.Match
}

func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

func IsOption(class string) bool {
	return class != "" && class[strings.LastIndex(class, `\`)+1:] == optionName
}

func (n Node) DeclaresNullableOption() bool {
	var written php.TypeName
	switch n.Kind() {
	case "Param", "Stmt_Property":
		written = php.Written(n.Node().Declared)
	case "Stmt_ClassMethod", "Stmt_Function":
		written = php.Written(n.Node().Returns)
	}

	return IsOption(written.NullableClass())
}

func (n Node) IsUnwrapOrNull() bool {
	if kind := n.Kind(); kind != "Expr_MethodCall" && kind != "Expr_NullsafeMethodCall" {
		return false
	}
	if name := n.Child("name"); name.Kind() != "Identifier" || name.Name() != "unwrapOr" {
		return false
	}
	arguments := php.Arguments(n.Match)

	return len(arguments) > 0 && php.IsNullConstant(arguments[0].Child("value"))
}
