package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func publishedContracts(call engine.Match, registrars []string) []string {
	method := CallName(call)
	if method == "" || !slices.ContainsFunc(registrars, func(registrar string) bool {
		return strings.HasSuffix(registrar, "::"+method)
	}) {
		return nil
	}
	owner := TypesOf(call.Codebase()).TypeOf(call.Child("var"))
	if call.Kind() == "Expr_StaticCall" {
		owner = StaticCallClass(call)
	}
	if !slices.Contains(registrars, owner+"::"+method) {
		return nil
	}
	var published []string
	for _, argument := range Arguments(call) {
		if name := ClassLiteral(argument.Child("value")); name != "" {
			published = append(published, name)
		}
	}

	return published
}

func ClassLiteral(fetch engine.Match) string {
	class, name := fetch.Child("class"), fetch.Child("name")
	if fetch.Kind() != "Expr_ClassConstFetch" || !isName(class) || name.Kind() != "Identifier" || name.Name() != "class" {
		return ""
	}

	return strings.TrimLeft(class.Name(), `\`)
}

func listedProjectClasses(list engine.Match) []string {
	n := Node{Match: list}
	if n.Kind() != "Expr_Array" {
		return nil
	}
	if parent := n.Parent().Kind(); parent != "Arg" && parent != "Stmt_Return" {
		return nil
	}
	if isPipelineList(list) || (n.Parent().Kind() == "Stmt_Return" && !n.EnclosingFunction().Exists() &&
		engine.InFolderNamed(n.FromProject(), "bootstrap")) {
		return nil
	}
	program := ProgramOf(n.Codebase())
	var listed []string
	for _, item := range n.ChildrenIn("items") {
		class := ClassLiteral(item.Child("value"))
		if _, declared := program.Declaration(class); class == "" || item.Child("key").Exists() || !declared {
			return nil
		}
		listed = append(listed, class)
	}

	return listed
}

func isPipelineList(list engine.Match) bool {
	parent := list.Parent()
	if parent.Kind() == "Arg" {
		call := parent.Parent()
		owner, method := TypesOf(list.Codebase()).Callee(call)
		declaration, _ := ProgramOf(list.Codebase()).Declaration(owner)
		_, declared := Method(declaration, method)

		return method == "runSteps" && declared
	}
	method := list.EnclosingFunction()
	if method.Name() != "pipes" && method.Name() != "steps" {
		return false
	}
	program := ProgramOf(list.Codebase())
	for _, name := range program.Contracts(EnclosingClassName(list)) {
		declaration, _ := program.Declaration(name)
		if _, declared := Method(declaration, method.Name()); declared {
			return true
		}
	}

	return false
}
