// Package phptypes is what the engine knows of jessegall/php-types, stated once.
package phptypes

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// optionName is the short name of the package's Option.
const optionName = "Option"

// Node is a match read in jessegall/php-types' terms.
type Node struct {
	engine.Match
}

// Decorate reads a match as a Node.
func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

// IsOption says whether a class is an Option, by its short name.
func IsOption(class string) bool {
	return class != "" && class[strings.LastIndex(class, `\`)+1:] == optionName
}

// DeclaresNullableOption says whether a parameter, property or function declares an Option that may also be null:
// `?Option`, `Option|null`.
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

// IsUnwrapOrNull says whether the node is `->unwrapOr(null)`: an Option turned back into a nullable.
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
