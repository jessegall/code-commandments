package engine

import "strings"

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

	read := m.grammar().NamespaceOf
	if read == nil || symbol == "" {
		return ""
	}

	return read(symbol)
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

// Before is what precedes the last separator, or nothing when there is none: the namespace part of a symbol whose
// language separates its parts so.
func Before(symbol, separator string) string {
	at := strings.LastIndex(symbol, separator)
	if at < 0 {
		return ""
	}

	return symbol[:at]
}
