package golang

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func nullableFields(declaration engine.Match) []engine.Match {
	fields := declaration.Child("type").Child("fields").ChildrenIn("list")
	return slices.DeleteFunc(fields, func(field engine.Match) bool { return !nullableDataField(field) })
}

func nullableDataField(field engine.Match) bool {
	typed := field.Typed()
	if typed == nil || typed.Kind != "pointer" || typed.Element == nil {
		return false
	}
	for _, declaration := range field.Codebase().Declarations(typed.Element.Name) {
		if declaration.TypeKind() == "struct" && len(declaration.Methods()) == 0 {
			return true
		}
	}
	return false
}
