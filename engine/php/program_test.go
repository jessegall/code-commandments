package php

import (
	"encoding/json"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/internal/shop"
)

func TestTheProgramKnowsItsDeclarationsAsPhpDoes(t *testing.T) {
	program := ProgramOf(shop.Codebase(t))
	var contracts []string
	for _, declaration := range program.Declarations() {
		node := declaration.Node()
		field := "implements"
		if node.Kind == "Stmt_Interface" {
			field = "extends"
		}
		for _, name := range names(node, field) {
			if !slices.Contains(contracts, name) {
				contracts = append(contracts, name)
			}
		}
	}
	slices.Sort(contracts)
	shop.Parity(t, "hierarchy", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		switch ask {
		case "isValueType":
			return program.IsValueType(node.Node().Declared)
		case "named":
			_, declared := program.Declaration(node.Name())

			return map[string]any{
				"isEnum":      program.IsEnum(node.Name()),
				"isInterface": program.IsInterface(node.Name()),
				"hasSubclass": program.HasSubclass(node.Name()),
				"declared":    declared,
			}
		case "class":
			return about(program, node.Node().Symbol, contracts)
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}

func about(program *Program, class string, contracts []string) map[string]any {
	implements := []string{}
	for _, contract := range contracts {
		if program.Implements(class, contract) {
			implements = append(implements, contract)
		}
	}
	traitMethods := []string{}
	for _, method := range program.TraitMethodsOf(class) {
		traitMethods = append(traitMethods, method.Name())
	}
	_, isClass := program.Class(class)
	declaration, declared := program.Declaration(class)
	var location any
	fields := []map[string]any{}
	if declared {
		location = []any{strings.TrimPrefix(declaration.File(), shop.Root+"/"), declaration.Node().Span.Start}
		for _, field := range Fields(declaration) {
			fields = append(fields, map[string]any{"name": field.Name, "type": Written(field.Type).Render(), "isPublic": field.IsPublic, "isPromoted": field.Promoted})
		}
	}

	return map[string]any{
		"ancestors":        orEmpty(program.Ancestors(class)),
		"implements":       implements,
		"isEnum":           program.IsEnum(class),
		"isInterface":      program.IsInterface(class),
		"hasSubclass":      program.HasSubclass(class),
		"classIsValueType": program.ClassIsValueType(class),
		"classNamed":       isClass,
		"declaration":      location,
		"usersOfTrait":     orEmpty(program.UsersOfTrait(class)),
		"traitMethods":     traitMethods,
		"fields":           fields,
	}
}

func orEmpty(names []string) []string {
	if names == nil {
		return []string{}
	}

	return names
}
