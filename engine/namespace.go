package engine

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Namespace is the namespace or module the node is declared in, as its language spells one: `Shop\Orders` in
// PHP, `shop.orders` in Python, `Shop.Orders` in C#, the file's path in TypeScript and Vue. It is read from the
// symbol of the outermost declaration around the node, and for code outside any declaration from the first
// declaration of its file; empty when the file declares nothing.
func (m Match) Namespace() string {
	if m.node == nil || m.file == nil {
		return ""
	}

	symbol := outermostSymbol(m)
	if symbol == "" {
		symbol = firstSymbolOf(m.Root())
	}

	return namespaceOf(m.file.Language(), symbol)
}

// outermostSymbol is the symbol of the outermost type or function around the node, itself included.
func outermostSymbol(m Match) string {
	symbol := ""

	for current := m; current.Exists(); current = current.Parent() {
		if (current.Is(TypeDeclaration) || current.Is(Function)) && current.node.Symbol != "" {
			symbol = current.node.Symbol
		}
	}

	return symbol
}

// firstSymbolOf is the symbol of the first type or function the tree declares.
func firstSymbolOf(root Match) string {
	for _, node := range root.Descendants() {
		if (node.Is(TypeDeclaration) || node.Is(Function)) && node.node.Symbol != "" {
			return node.node.Symbol
		}
	}

	return ""
}

// namespaceOf is the namespace part of a declaration's symbol, in the spelling of its language.
func namespaceOf(language contract.Language, symbol string) string {
	switch language {
	case contract.PHP:
		symbol, _, _ = strings.Cut(symbol, "::")

		return before(strings.TrimSuffix(symbol, "()"), `\`)
	case contract.CSharp:
		symbol, _, _ = strings.Cut(strings.TrimPrefix(symbol, "global::"), "(")

		return before(symbol, ".")
	case contract.TypeScript, contract.Vue:
		return before(symbol, "#")
	default:
		return before(symbol, ".")
	}
}

// before is what precedes the last separator, or nothing when there is none.
func before(symbol, separator string) string {
	at := strings.LastIndex(symbol, separator)
	if at < 0 {
		return ""
	}

	return symbol[:at]
}
