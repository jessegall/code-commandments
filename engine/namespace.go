package engine

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

func (m Match) Namespace() string {
	if m.node == nil || m.file == nil {
		return ""
	}
	if m.file.Module != "" {
		return m.file.Module
	}

	for current := m; current.Exists(); current = current.Parent() {
		if current.Kind() == "module" {
			return current.Name()
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
	separator := contract.NamespaceSeparator(language)
	if separator == "" {
		return name
	}
	segments := strings.SplitN(strings.TrimLeft(name, separator), separator, 3)
	if len(segments) < 2 {
		return segments[0]
	}

	return segments[0] + separator + segments[1]
}

// Within is whether a namespace is the one named or nested inside it, whatever separator the language writes: `App\Ui`
// within `App`, `shop.cart` within `shop`; every namespace is within the empty one.
func Within(name, namespace string) bool {
	name, namespace = strings.ToLower(strings.TrimLeft(name, `\`)), strings.ToLower(strings.Trim(namespace, `\`))
	if namespace == "" || name == namespace {
		return true
	}

	return strings.HasPrefix(name, namespace) && strings.ContainsRune(`\./:`, rune(name[len(namespace)]))
}
