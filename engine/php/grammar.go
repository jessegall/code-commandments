package php

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.PHP, engine.Grammar{
		Arguments:     values,
		Members:       engine.InFields("stmts"),
		Parameters:    engine.InFields("params"),
		Extends:       engine.InFields("extends"),
		Implements:    engine.InFields("implements"),
		Traits:        traitNames,
		DeclaredName:  propertyName,
		ClassOf:       classOf,
		Annotations:   attributes,
		TypeKind:      engine.Kinds(map[string]string{"Stmt_Class": "class", "Stmt_Interface": "interface", "Stmt_Enum": "enum", "Stmt_Trait": "trait"}),
		ReturnType:    engine.InField("returnType"),
		ParameterType: engine.InField("type"),
		Constructs:    constructed,
		Callers:       callers,
		DocTags:       phpDocTags,
		BodyHash:      func(function engine.Match) string { return Node{Match: function}.BodyHash() },
		TestFile:      testFile,
		Implicit:      magic,
		Labels:        func(name engine.Match) bool { return name.Parent().Kind() == "Arg" && name.Node().Field == "name" },
		NamespaceOf: func(symbol string) string {
			class, _, _ := strings.Cut(symbol, "::")

			return engine.Before(strings.TrimSuffix(class, "()"), `\`)
		},
		Continues: engine.KindIn("Stmt_ElseIf"),
	})

}

// testFile says whether a PHP file is a test's: PHPUnit's `*Test.php`, or any file under a tests folder.
func testFile(path, _ string) bool {
	return strings.HasSuffix(path, "Test.php") || engine.InFolderNamed(path, "tests", "test")
}

// values are the values a call's arguments hand it, named and unpacked ones alike; a first-class callable's
// `(...)` hands none.
func values(call engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range call.ChildrenIn("args") {
		if value := argument.Child("value"); value.Exists() {
			handed = append(handed, value)
		}
	}

	return handed
}

// constructed is the class a `new` names.
func constructed(construction engine.Match) engine.Match {
	if construction.Kind() != "Expr_New" {
		return engine.Match{}
	}

	return construction.Child("class")
}

// callers are the calls reaching a function or a method: a method's the index finds sent to its class or a
// subclass, static or not, and a function's the calls naming it.
func callers(function engine.Match) []engine.Match {
	switch function.Kind() {
	case "Stmt_Function":
		return IndexOf(function.Codebase()).FunctionCallersOf(function)
	case "Stmt_ClassMethod":
		if class := function.EnclosingType(); class.Node().Symbol != "" {
			return IndexOf(function.Codebase()).CallersOf(class.Node().Symbol, function.Name())
		}
	}

	return nil
}

// attributes are the names of the attributes a declaration carries, in every group.
func attributes(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, group := range declaration.ChildrenIn("attrGroups") {
		for _, attribute := range group.ChildrenIn("attrs") {
			names = append(names, attribute.Child("name"))
		}
	}

	return names
}

// magic says whether PHP itself calls the method: a constructor, a destructor or any other magic method.
func magic(declaration engine.Match) bool {
	return declaration.Kind() == "Stmt_ClassMethod" && Node{Match: declaration}.IsMagicMethod()
}

// phpDocTags are the tags of a declaration's PHPDoc comments, each by its bare name: `@param $x` is param.
func phpDocTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		for _, tag := range docTags(comment) {
			name, _, _ := strings.Cut(strings.TrimPrefix(tag, "@"), " ")
			tags = append(tags, name)
		}
	}

	return tags
}
