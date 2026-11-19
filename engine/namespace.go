package engine

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

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

func ModuleOf(language contract.Language, name string) string {
	separator := grammarOf(language).NamespaceSeparator
	if separator == "" {
		return name
	}
	segments := strings.SplitN(strings.TrimLeft(name, separator), separator, 3)
	if len(segments) < 2 {
		return segments[0]
	}

	return segments[0] + separator + segments[1]
}
