package frontend

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	for _, language := range []contract.Language{contract.Vue, contract.TypeScript} {
		engine.ListAs(language, engine.Lists{
			Arguments:     engine.InFields("arguments"),
			Members:       engine.InFields("members"),
			Extends:       heritage("extends"),
			Implements:    heritage("implements"),
			Annotations:   decorators,
			TypeKind:      engine.Kinds(map[string]string{"ClassDeclaration": "class", "ClassExpression": "class", "InterfaceDeclaration": "interface", "EnumDeclaration": "enum"}),
			ReturnType:    engine.InField("type"),
			ParameterType: engine.InField("type"),
			Constructs:    engine.OfKind("NewExpression", "expression"),
		})
	}
}

// heritage reads the types a declaration's clause of the keyword names: `extends` or `implements`. The tree
// keeps no fact of which a clause is, so its first token says.
func heritage(keyword string) func(engine.Match) []engine.Match {
	return func(declaration engine.Match) []engine.Match {
		var named []engine.Match
		for _, clause := range declaration.ChildrenIn("heritageClauses") {
			if !strings.HasPrefix(clause.Written(), keyword) {
				continue
			}

			for _, written := range clause.ChildrenIn("types") {
				named = append(named, written.Child("expression"))
			}
		}

		return named
	}
}

// decorators are the names of the decorators a declaration carries: a called one by what it calls.
func decorators(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, modifier := range declaration.ChildrenIn("modifiers") {
		if modifier.Kind() != "Decorator" {
			continue
		}

		decorator := modifier.Child("expression")
		if decorator.Is(engine.Call) {
			decorator = decorator.Child("expression")
		}

		names = append(names, decorator)
	}

	return names
}
