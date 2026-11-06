package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func traitNames(declaration engine.Match) []engine.Match {
	var traits []engine.Match
	for _, member := range declaration.ChildrenIn("stmts") {
		if member.Kind() == "Stmt_TraitUse" {
			traits = append(traits, member.ChildrenIn("traits")...)
		}
	}

	return traits
}

func typeOf(node engine.Match) string {
	return strings.TrimLeft(TypesOf(node.Codebase()).TypeOf(node), `?\`)
}

func classOf(node engine.Match) string {
	switch {
	case isName(node):
		return strings.TrimLeft(namedClass(node, node), `\`)
	case node.Kind() == "Expr_New" && isName(node.Child("class")):
		return strings.TrimLeft(namedClass(node, node.Child("class")), `\`)
	case node.Kind() == "Expr_ClassConstFetch" && strings.EqualFold(node.Child("name").Name(), "class") && isName(node.Child("class")):
		return strings.TrimLeft(namedClass(node, node.Child("class")), `\`)
	}
	held := strings.TrimLeft(TypesOf(node.Codebase()).TypeOf(node), `?\`)
	if strings.ContainsAny(held, "|&") || !IsClassName(held) {
		return ""
	}

	return held
}

func propertyName(statement engine.Match) string {
	properties := statement.ChildrenIn("props")
	if statement.Kind() != "Stmt_Property" || len(properties) != 1 {
		return ""
	}

	return strings.TrimPrefix(properties[0].Name(), "$")
}
