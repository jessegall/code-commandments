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
		Extends:       engine.InFields("extends"),
		Implements:    engine.InFields("implements"),
		Annotations:   attributes,
		TypeKind:      engine.Kinds(map[string]string{"Stmt_Class": "class", "Stmt_Interface": "interface", "Stmt_Enum": "enum", "Stmt_Trait": "trait"}),
		ReturnType:    engine.InField("returnType"),
		ParameterType: engine.InField("type"),
		Constructs:    constructed,
		Callers:       callers,
	})
}

// values are the values a call's arguments hand it, named and unpacked ones alike.
func values(call engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range call.ChildrenIn("args") {
		handed = append(handed, argument.Child("value"))
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

// functionCallers are the calls of a function: naming it whole, or, from its own namespace, by its short name,
// which PHP resolves to it before any global function of that name.
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
			if strings.EqualFold(call.Namespace(), namespace) {
				calls = append(calls, call)
			}
		}
	}

	return calls
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
