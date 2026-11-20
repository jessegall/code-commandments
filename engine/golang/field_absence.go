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

func passesFieldPointer(read engine.Match) bool {
	call := read.Parent()
	if !call.Is(engine.Call) || read.Field() != "args" || call.TargetSymbol() == "" {
		return false
	}
	position := slices.IndexFunc(call.Arguments(), func(argument engine.Match) bool { return argument.Node() == read.Node() })
	if position < 0 {
		return false
	}
	for _, function := range read.Codebase().Declarations(call.TargetSymbol()) {
		for _, parameter := range function.Parameters() {
			count := max(1, len(parameter.ChildrenIn("names")))
			if position >= count {
				position -= count
				continue
			}
			typed := parameter.Typed()
			return typed != nil && typed.Kind == "pointer"
		}
	}

	return false
}
