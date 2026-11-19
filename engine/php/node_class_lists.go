package php

import (
	"path"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

const leastListedClasses = 5

func ClassLiteral(fetch engine.Match) string {
	class, name := fetch.Child("class"), fetch.Child("name")
	if fetch.Kind() != "Expr_ClassConstFetch" || !isName(class) || name.Kind() != "Identifier" || name.Name() != "class" {
		return ""
	}

	return strings.TrimLeft(class.Name(), `\`)
}

func (n Node) IsHandedOver() bool {
	return n.Parent().Kind() == "Arg" || n.Parent().Kind() == "Stmt_Return"
}

func (n Node) ListsEveryClassOfItsFolders() bool {
	listed := n.listedProjectClasses()
	if len(listed) < leastListedClasses {
		return false
	}
	program := ProgramOf(n.Codebase())
	folders := map[string]bool{}
	for _, class := range listed {
		declaration, _ := program.Declaration(class)
		folders[path.Dir(declaration.File())] = true
	}
	for _, shared := range sharedSupertypes(program, listed) {
		if everyClassOfTheTypeIsListed(program, shared, folders, listed) {
			return true
		}
	}

	return false
}

func (n Node) listedProjectClasses() []string {
	if n.Kind() != "Expr_Array" {
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

func sharedSupertypes(program *Program, classes []string) []string {
	shared := program.Supertypes(classes[0])
	for _, class := range classes[1:] {
		supertypes := program.Supertypes(class)
		shared = slices.DeleteFunc(shared, func(each string) bool { return !slices.Contains(supertypes, each) })
	}

	return shared
}

func everyClassOfTheTypeIsListed(program *Program, shared string, folders map[string]bool, listed []string) bool {
	for class, declaration := range program.Declarations() {
		if declaration.Kind() != "Stmt_Class" || declaration.HasModifier("abstract") || !folders[path.Dir(declaration.File())] {
			continue
		}
		if program.IsA(class, shared) && !slices.Contains(listed, class) {
			return false
		}
	}

	return true
}
