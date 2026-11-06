package frontend

import (
	"path"
	"slices"
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
			TypeOf:        func(node engine.Match) string { return typescript.Of(node).TypeName() },
			Receiver:      receiver,
			Condition:     condition,
			DocTags:       jsDocTags,
			WritesAttribute: func(element engine.Match, name string) bool {
				return vue.Of(element).Writes(name)
			},
			BodyHash: func(function engine.Match) string { return typescript.Of(function).BodyHash() },
			NodeHash: func(node engine.Match, normalize bool) string {
				return typescript.Of(node).NodeHash(normalize)
			},
			TestFile:    testFile,
			Implicit:    engine.KindIn("Constructor"),
			NamespaceOf: func(symbol string) string { return engine.Before(symbol, "#") },
			Labels: func(name engine.Match) bool {
				return name.Parent().Kind() == "PropertyAssignment" && name.Node().Field == "name"
			},
			Continues: func(branch engine.Match) bool {
				return branch.Kind() == "IfStatement" && branch.Node().Field == "elseStatement"
			},
		})

	}
}

func testFile(file, _ string) bool {
	name := path.Base(strings.ReplaceAll(file, `\`, "/"))

	return strings.Contains(name, ".test.") || strings.Contains(name, ".spec.") ||
		engine.InFolderNamed(file, "__tests__", "tests", "test")
}

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

func jsDocTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		tags = append(tags, atTags(comment)...)
	}

	return tags
}

var sentTo = []string{"PropertyAccessExpression", "ElementAccessExpression"}

func receiver(node engine.Match) engine.Match {
	if slices.Contains(sentTo, node.Kind()) {
		return node.Child("expression")
	}

	if node.Kind() != "CallExpression" && node.Kind() != "NewExpression" {
		return engine.Match{}
	}

	return receiver(node.Child("expression"))
}

var asked = map[string]string{
	"IfStatement":           "expression",
	"WhileStatement":        "expression",
	"DoStatement":           "expression",
	"SwitchStatement":       "expression",
	"ConditionalExpression": "condition",
	"ForStatement":          "condition",
}

func condition(held engine.Match) engine.Match {
	field, decides := asked[held.Kind()]
	if !decides {
		return engine.Match{}
	}

	return held.Child(field)
}
