package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var memberReads = map[string]bool{"Expr_PropertyFetch": true, "Expr_NullsafePropertyFetch": true}

var memberCalls = map[string]bool{"Expr_MethodCall": true, "Expr_NullsafeMethodCall": true}

func memberLookup(match engine.Match) (string, func(string) bool) {
	n := Node{Match: match}
	if !memberReads[n.Kind()] && !memberCalls[n.Kind()] {
		return "", nil
	}
	name, receiver := n.Child("name"), n.Child("var")
	if name.Kind() != "Identifier" || variableName(receiver) == "this" || n.isNarrowedByInstanceof(receiver) {
		return "", nil
	}
	class := receiver.TypeName()
	if class == "" || ProgramOf(n.Codebase()).IsEnum(class) {
		return "", nil
	}
	if memberReads[n.Kind()] {
		property, magic := n.declaresProperty(name.Name()), n.declaresMethod("__get")

		return class, func(owner string) bool { return property(owner) || magic(owner) }
	}
	method, magic := n.declaresMethod(name.Name()), n.declaresMethod("__call")

	return class, func(owner string) bool { return method(owner) || magic(owner) }
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
