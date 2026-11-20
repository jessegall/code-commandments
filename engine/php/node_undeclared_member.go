package php

import (
	"slices"
	"strings"

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
	if name.Kind() != "Identifier" || variableName(receiver) == "this" || isAssignmentTarget(n.Match) || n.isNarrowedByInstanceof(receiver) {
		return "", nil
	}
	types := TypesOf(n.Codebase())
	class := types.TypeAtRead(receiver)
	if class == "" || ProgramOf(n.Codebase()).IsEnum(class) {
		return "", nil
	}
	member, magic := n.declaresMethod(name.Name()), n.declaresMethod("__call")
	if memberReads[n.Kind()] {
		member, magic = n.declaresProperty(name.Name()), n.declaresMethod("__get")
	}

	return class, func(owner string) bool {
		return member(owner) || magic(owner) || types.hasUnreadTrait(owner)
	}
}

func (n Node) declaresMethod(method string) func(string) bool {
	types, program := TypesOf(n.Codebase()), ProgramOf(n.Codebase())
	sameName := func(written string) bool { return strings.EqualFold(written, method) }

	return func(class string) bool {
		return types.memberDeclaredBy(class, map[string]bool{}, func(owner string) bool {
			if _, declared := types.returnType[owner][method]; declared {
				return true
			}
			for written := range types.returnType[owner] {
				if sameName(written) {
					return true
				}
			}
			declaration, _ := program.Declaration(owner)
			return slices.ContainsFunc(DocumentedMethods(declaration), sameName)
		}) != ""
	}
}

func (n Node) declaresProperty(field string) func(string) bool {
	types, program := TypesOf(n.Codebase()), ProgramOf(n.Codebase())

	return func(class string) bool {
		declaration, _ := program.Declaration(class)

		return types.fieldDeclaredBy(class, field) != "" || slices.Contains(DocumentedProperties(declaration), field)
	}
}

func (n Node) isNarrowedByInstanceof(receiver engine.Match) bool {
	function := enclosingFunction(n.Match)

	return slices.ContainsFunc(function.Descendants(), func(test engine.Match) bool {
		return test.Kind() == "Expr_Instanceof" && test.Child("expr").SameSyntax(receiver)
	})
}
