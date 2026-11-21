package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

func IsUntypedCollection(typed *contract.Type) bool {
	value := typed.Element
	if value == nil && (typed.Kind == "map" || typed.Kind == "array") && len(typed.Args) > 0 {
		value = typed.Args[len(typed.Args)-1]
	}
	if value == nil {
		return false
	}
	return slices.Contains([]string{"any", "unknown", "object", "interface{}"}, value.Text)
}

