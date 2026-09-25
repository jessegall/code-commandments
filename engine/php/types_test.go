package php

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestTypesResolveEveryExpressionAsPhpDoes(t *testing.T) {
	codebase := shop.Codebase(t)
	types := TypesOf(codebase)
	types.Fill(codebase)
	program := ProgramOf(codebase)
	shop.Parity(t, "types", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		switch ask {
		case "typeOf":
			if resolved := node.Node().Resolved; resolved != nil {
				return resolved.Name
			}

			return nil
		case "call":
			arity := 0
			for _, arg := range node.Children() {
				if arg.Node().Field == "args" {
					arity++
				}
			}

			return method(types, types.TypeOf(node.Child("var")), node.Child("name").Name(), arity)
		case "members":
			return members(program, types, node.Node().Symbol)
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}

func members(program *Program, types *Types, class string) map[string]any {
	fields, methods := []string{"nope"}, []string{"nope"}
	for _, owner := range append([]string{class}, program.Ancestors(class)...) {
		declaration, ok := program.Declaration(owner)
		if !ok {
			continue
		}
		for _, field := range Fields(declaration) {
			fields = append(fields, field.Name)
		}
		for _, method := range Methods(declaration) {
			methods = append(methods, method.Name())
		}
		for _, trait := range traitsOf(declaration.Node()) {
			if used, ok := program.Declaration(trait); ok {
				for _, method := range Methods(used) {
					methods = append(methods, method.Name())
				}
			}
		}
	}
	answers := map[string]map[string]any{"fields": {}, "methods": {}}
	for _, field := range fields {
		answers["fields"][field] = map[string]any{
			"declaringClassOf":    orNil(types.DeclaringClassOf(class, field)),
			"propertyTypeOf":      orNil(types.PropertyTypeOf(class, field)),
			"collectionElementOf": orNil(types.CollectionElementOf(class, field)),
		}
	}
	for _, name := range methods {
		answers["methods"][name] = method(types, class, name, 4)
	}

	return map[string]any{"fields": answers["fields"], "methods": answers["methods"]}
}

func method(types *Types, class, name string, arity int) map[string]any {
	var paramTypes, nullables []any
	for position := range max(1, arity) {
		paramTypes = append(paramTypes, orNil(types.ParamTypeOf(class, name, position)))
		if nullable, known := types.ParamIsNullable(class, name, position); known {
			nullables = append(nullables, nullable)
		} else {
			nullables = append(nullables, nil)
		}
	}

	return map[string]any{
		"declaringClassOfMethod": orNil(types.DeclaringClassOfMethod(class, name)),
		"methodIsVariadic":       types.MethodIsVariadic(class, name),
		"paramTypeOf":            paramTypes,
		"paramIsNullable":        nullables,
	}
}
