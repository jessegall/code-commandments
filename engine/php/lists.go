package php

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ListAs(contract.PHP, engine.Lists{
		Arguments:     values,
		Members:       engine.InFields("stmts"),
		Parameters:    engine.InFields("params"),
		Extends:       engine.InFields("extends"),
		Implements:    engine.InFields("implements"),
		Annotations:   attributes,
		TypeKind:      engine.Kinds(map[string]string{"Stmt_Class": "class", "Stmt_Interface": "interface", "Stmt_Enum": "enum", "Stmt_Trait": "trait"}),
		ReturnType:    engine.InField("returnType"),
		ParameterType: engine.InField("type"),
		Constructs:    constructed,
		Callers:       callers,
		DocTags:       engine.AtTagsOf,
		BodyHash:      func(function engine.Match) string { return Node{Match: function}.BodyHash() },
		TestFile:      testFile,
		Implicit:      magic,
		Continues:     engine.KindIn("Stmt_ElseIf"),
	})

	engine.Predicates(contract.PHP,
		engine.Predicate{Name: "constructor", Says: "it declares a constructor", Holds: func(m engine.Match) bool { return Node{Match: m}.IsConstructorDeclaration() }},
		engine.Predicate{Name: "coalesce", Says: "it is a `??` expression", Holds: func(m engine.Match) bool { return Node{Match: m}.IsCoalesce() }},
		engine.Predicate{Name: "returnedValue", Says: "it is the value a return statement returns", Holds: func(m engine.Match) bool { return Node{Match: m}.IsReturnedValue() }},
		engine.Predicate{Name: "typeNarrowingGuard", Says: "it is an outermost `&&` of two or more instanceof checks", Holds: func(m engine.Match) bool { return Node{Match: m}.IsTypeNarrowingGuard() }},
		engine.Predicate{Name: "inNamedConstructor", Says: "the function around it builds an instance of its own class", Holds: func(m engine.Match) bool { return Node{Match: m}.IsWithinNamedConstructor() }},
	)
}

// testFile says whether a PHP file is a test's: PHPUnit's `*Test.php`, or any file under a tests folder.
func testFile(path string) bool {
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
		return functionCallers(function)
	case "Stmt_ClassMethod":
		if class := function.EnclosingType(); class.Node().Symbol != "" {
			return IndexOf(function.Codebase()).CallersOf(class.Node().Symbol, function.Name())
		}
	}

	return nil
}

// functionCallers are the calls of a function: naming it whole, or by its short name from its own namespace, which
// PHP resolves to it before any global function of that name, or, for a global function, from a namespace that
// declares none of that name, where PHP falls back to it.
func functionCallers(function engine.Match) []engine.Match {
	qualified := strings.TrimSuffix(function.Node().Symbol, "()")
	namespace, short := "", qualified
	if at := strings.LastIndex(qualified, `\`); at >= 0 {
		namespace, short = qualified[:at], qualified[at+1:]
	}

	var calls []engine.Match
	for _, call := range functionCalls(function.Codebase())[strings.ToLower(short)] {
		name := call.Child("name")

		switch name.Kind() {
		case "Name_FullyQualified":
			if strings.EqualFold(name.Name(), qualified) {
				calls = append(calls, call)
			}
		case "Name":
			if strings.EqualFold(call.Namespace(), namespace) || namespace == "" && !declaresFunction(call, short) {
				calls = append(calls, call)
			}
		}
	}

	return calls
}

// declaresFunction says whether the call's own namespace declares a function of the short name, which PHP calls
// before falling back to the global one.
func declaresFunction(call engine.Match, short string) bool {
	return len(call.Codebase().Declarations(call.Namespace()+`\`+short+"()")) > 0
}

// functionCalls are the codebase's calls of a function by name, by the last part of that name in lower case, as
// PHP spells a function's name in any case.
func functionCalls(codebase *engine.Codebase) map[string][]engine.Match {
	return engine.Analysis(codebase, "php-function-calls", func(codebase *engine.Codebase) map[string][]engine.Match {
		byName := map[string][]engine.Match{}
		for _, call := range codebase.WhereCall().Get() {
			if call.Kind() != "Expr_FuncCall" {
				continue
			}

			name := call.Child("name").Name()
			short := strings.ToLower(name[strings.LastIndex(name, `\`)+1:])
			byName[short] = append(byName[short], call)
		}

		return byName
	})
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

// magic says whether PHP itself calls the method: a constructor, a destructor or any other magic method, each named
// with two leading underscores.
func magic(declaration engine.Match) bool {
	return declaration.Kind() == "Stmt_ClassMethod" && strings.HasPrefix(declaration.Name(), "__")
}
