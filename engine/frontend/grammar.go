package frontend

import (
	"path"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/engine/vue"
)

func init() {
	for _, language := range []contract.Language{contract.Vue, contract.TypeScript} {
		engine.ReadAs(language, engine.Grammar{
			Arguments:     engine.InFields("arguments"),
			Members:       engine.InFields("members"),
			Parameters:    engine.InFields("parameters"),
			Extends:       heritage("extends"),
			Implements:    heritage("implements"),
			Annotations:   decorators,
			TypeKind:      engine.Kinds(map[string]string{"ClassDeclaration": "class", "ClassExpression": "class", "InterfaceDeclaration": "interface", "EnumDeclaration": "enum"}),
			ReturnType:    engine.InField("type"),
			ParameterType: engine.InField("type"),
			Constructs:    engine.OfKind("NewExpression", "expression"),
			DocTags:       jsDocTags,
			WritesAttribute: func(element engine.Match, name string) bool {
				return vue.Of(element).Writes(name)
			},
			BodyHash:      func(function engine.Match) string { return typescript.Of(function).BodyHash() },
			TestFile:      testFile,
			Implicit:      engine.KindIn("Constructor"),
			NamespaceOf:   func(symbol string) string { return engine.Before(symbol, "#") },
			Labels: func(name engine.Match) bool {
				return name.Parent().Kind() == "PropertyAssignment" && name.Node().Field == "name"
			},
			Continues: func(branch engine.Match) bool {
				return branch.Kind() == "IfStatement" && branch.Node().Field == "elseStatement"
			},
		})

	}
}

// testFile says whether a frontend file is a test's: `*.test.*` or `*.spec.*`, as Vitest and Jest collect them,
// or any file under a __tests__ or tests folder.
func testFile(file, _ string) bool {
	name := path.Base(strings.ReplaceAll(file, `\`, "/"))

	return strings.Contains(name, ".test.") || strings.Contains(name, ".spec.") ||
		engine.InFolderNamed(file, "__tests__", "tests", "test")
}

// heritage reads the types a declaration's clause of the keyword names: `extends` or `implements`, the clause's
// operator.
func heritage(keyword string) func(engine.Match) []engine.Match {
	return func(declaration engine.Match) []engine.Match {
		var named []engine.Match
		for _, clause := range declaration.ChildrenIn("heritageClauses") {
			if clause.Node().Operator != keyword {
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

// atTags are the tags a JSDoc comment carries: each line that opens with `@` names one, its bare name
// the word after the `@`.
func atTags(comment string) []string {
	var tags []string
	for _, line := range strings.Split(comment, "\n") {
		line = strings.TrimLeft(strings.TrimSpace(line), "/*")
		word, _, _ := strings.Cut(strings.TrimSpace(line), " ")
		if name, tagged := strings.CutPrefix(word, "@"); tagged && name != "" {
			tags = append(tags, strings.TrimRight(name, "{}"))
		}
	}

	return tags
}

// jsDocTags are the tags of a declaration's JSDoc comments.
func jsDocTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		tags = append(tags, atTags(comment)...)
	}

	return tags
}
