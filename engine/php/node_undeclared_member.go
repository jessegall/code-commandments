package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var memberReads = map[string]bool{"Expr_PropertyFetch": true, "Expr_NullsafePropertyFetch": true}

var memberCalls = map[string]bool{"Expr_MethodCall": true, "Expr_NullsafeMethodCall": true}

func (n Node) ReachesAMemberItsTypeLacks() bool {
	if !memberReads[n.Kind()] && !memberCalls[n.Kind()] {
		return false
	}
	name, receiver := n.Child("name"), n.Child("var")
	if name.Kind() != "Identifier" || variableName(receiver) == "this" || n.isNarrowedByInstanceof(receiver) {
		return false
	}
	class := TypesOf(n.Codebase()).TypeOf(receiver)
	owners, known := n.wholeTypeOf(class)
	if !known {
		return false
	}
	if memberReads[n.Kind()] {
		return !slices.ContainsFunc(owners, n.declaresProperty(name.Name())) && !slices.ContainsFunc(owners, n.declaresMethod("__get"))
	}

	return !slices.ContainsFunc(owners, n.declaresMethod(name.Name())) && !slices.ContainsFunc(owners, n.declaresMethod("__call"))
}

func (n Node) wholeTypeOf(class string) ([]string, bool) {
	program := ProgramOf(n.Codebase())
	if class == "" || program.IsEnum(class) {
		return nil, false
	}
	owners := append([]string{class}, engine.Supertypes(program, class)...)

	return owners, !slices.ContainsFunc(owners, func(named string) bool {
		_, declared := program.Declaration(named)

		return !declared
	})
}

func (n Node) declaresMethod(method string) func(string) bool {
	types, program := TypesOf(n.Codebase()), ProgramOf(n.Codebase())

	return func(class string) bool {
		declaration, _ := program.Declaration(class)

		return types.methodDeclaredBy(class, method, map[string]bool{}) != "" || slices.Contains(DocumentedMethods(declaration), method)
	}
}

func (n Node) declaresProperty(field string) func(string) bool {
	program := ProgramOf(n.Codebase())

	return func(class string) bool {
		declaration, _ := program.Declaration(class)

		return slices.ContainsFunc(Fields(declaration), func(declared Field) bool { return declared.Name == field }) || slices.Contains(DocumentedProperties(declaration), field)
	}
}

func (n Node) isNarrowedByInstanceof(receiver engine.Match) bool {
	function := enclosingFunction(n.Match)

	return slices.ContainsFunc(function.Descendants(), func(test engine.Match) bool {
		return test.Kind() == "Expr_Instanceof" && test.Child("expr").SameSyntax(receiver)
	})
}
