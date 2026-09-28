package frontend

import (
	"path"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
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
			DocTags:       engine.AtTagsOf,
			BodyHash:      func(function engine.Match) string { return typescript.Of(function).BodyHash() },
			TestFile:      testFile,
		})

		engine.Predicates(language,
			engine.Predicate{Name: "optional", Says: "a field or parameter may be missing: written `x?`, or typed to admit null or undefined", Holds: func(m engine.Match) bool { return typescript.Of(m).IsOptional() }},
			engine.Predicate{Name: "absence", Says: "it is the literal null or undefined", Holds: func(m engine.Match) bool { return typescript.Of(m).IsAbsence() }},
		)
	}
}

// testFile says whether a frontend file is a test's: `*.test.*` or `*.spec.*`, as Vitest and Jest collect them,
// or any file under a __tests__ or tests folder.
func testFile(file string) bool {
	name := path.Base(strings.ReplaceAll(file, `\`, "/"))

	return strings.Contains(name, ".test.") || strings.Contains(name, ".spec.") ||
		engine.InFolderNamed(file, "__tests__", "tests", "test")
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
