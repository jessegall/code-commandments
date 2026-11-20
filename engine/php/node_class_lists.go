package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

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
