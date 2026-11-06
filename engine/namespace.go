package engine

import "strings"

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

func outermostSymbol(m Match) string {
	symbol := ""

	for current := m; current.Exists(); current = current.Parent() {
		if (current.Is(TypeDeclaration) || current.Is(Function)) && current.node.Symbol != "" {
			symbol = current.node.Symbol
		}
	}

	return symbol
}

func firstSymbolOf(root Match) string {
	for _, node := range root.Descendants() {
		if (node.Is(TypeDeclaration) || node.Is(Function)) && node.node.Symbol != "" {
			return node.node.Symbol
		}
	}

	return ""
}

func Before(symbol, separator string) string {
	at := strings.LastIndex(symbol, separator)
	if at < 0 {
		return ""
	}

	return symbol[:at]
}
