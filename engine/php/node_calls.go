package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsEnclosedInClass() bool {
	return n.EnclosingClassLike().Exists()
}

func (n Node) firstPassed() engine.Match {
	passed := n.ChildrenIn("args")
	if n.Kind() != "Expr_FuncCall" || len(passed) == 0 || passed[0].Kind() != "Arg" {
		return engine.Match{}
	}

	return passed[0]
}

func (n Node) FirstArgIsClassLiteral() bool {
	value := n.firstPassed().Child("value")

	return value.Kind() == "Expr_ClassConstFetch" || value.Kind() == "Scalar_String"
}

func (n Node) FirstArgClassLiteral() string {
	value := n.firstPassed().Child("value")
	switch value.Kind() {
	case "Scalar_String":
		text, _ := value.Text()

		return text
	case "Expr_ClassConstFetch":
		class := value.Child("class")
		if !isName(class) {
			return ""
		}
		if strings.EqualFold(class.Name(), "static") || strings.EqualFold(class.Name(), "self") {
			return EnclosingClassName(n.Match)
		}

		return class.Name()
	}

	return ""
}

func (n Node) IsEnclosingClassResolution() bool {
	enclosing := EnclosingClassName(n.Match)

	return enclosing != "" && n.FirstArgClassLiteral() == enclosing
}

func (n Node) StringArgument(position int) (string, bool) {
	value := n.Argument(position)
	if value.Kind() != "Scalar_String" {
		return "", false
	}

	return value.Text()
}

func (n Node) ArgumentCount() int {
	return len(Arguments(n.Match))
}

func (n Node) IsReCoercedToString() bool {
	parent := n.Parent()
	if name := parent.Child("name"); parent.Kind() == "Expr_MethodCall" && n.Node().Field == "var" && name.Kind() == "Identifier" && name.Name() == "toString" {
		return true
	}

	return parent.Kind() == "Expr_Cast_String" && n.Node().Field == "expr"
}

func (n Node) ReceiverMutatedNearby() bool {
	receiver := n.Child("var")
	if receiver.Kind() != "Expr_Variable" || receiver.Name() == "this" || receiver.Name() == "" {
		return false
	}

	return slices.ContainsFunc(Trace(receiver), func(interaction Interaction) bool { return interaction.Kind == PropertyWrite })
}

func (n Node) IsUsedOn(classes ...string) bool {
	program := ProgramOf(n.Codebase())
	enclosing, receiver := EnclosingClassName(n.Match), ReceiverTypeOf(n.Match)
	for _, class := range classes {
		target := strings.TrimLeft(class, `\`)
		if enclosing != "" && program.IsA(enclosing, target) {
			return false
		}
		if receiver != "" && program.IsA(receiver, target) {
			return true
		}
	}

	return false
}

func IsEverProduced(codebase *engine.Codebase, class string, statically ...string) bool {
	if class == "" {
		return false
	}
	program := ProgramOf(codebase)
	built := In(codebase).WhereKind("Expr_New").Where(func(m engine.Match) bool {
		return program.IsA(Node{Match: m}.NewClassName(), class)
	}).Count() > 0
	if built || len(statically) == 0 {
		return built
	}

	return In(codebase).WhereKind("Expr_StaticCall").Where(func(m engine.Match) bool {
		call := Node{Match: m}

		return slices.Contains(statically, call.StaticCallMethod()) && program.IsA(call.StaticCallClass(), class)
	}).Count() > 0
}

func (n Node) IsPropertyFetchNamed(name string) bool {
	return isPropertyRead(n.Match) && n.Child("name").Kind() == "Identifier" && n.Child("name").Name() == name
}

func (n Node) IsThisPropertyAssignment() bool {
	return n.Kind() == "Expr_Assign" && Node{Match: n.Child("var")}.IsOwnPropertyRead()
}

func (n Node) AssignmentReferencesLocalVariable() bool {
	return n.Kind() == "Expr_Assign" && slices.ContainsFunc(withDescendants(n.Child("expr")), func(node engine.Match) bool {
		return node.Kind() == "Expr_Variable" && node.Name() != "this"
	})
}

func (n Node) IsWithinBranch() bool {
	for at := n.Up(); at.Exists() && !at.IsFunctionLike(); at = at.Up() {
		switch at.Kind() {
		case "Stmt_If", "Expr_Match", "Expr_Ternary", "Stmt_Foreach", "Stmt_For", "Stmt_While", "Stmt_Do":
			return true
		}
	}

	return false
}

func (n Node) HasAttribute(shortNames ...string) bool {
	return HasAttribute(n.Match, shortNames...)
}

func (n Node) IsAttributeNamed(name string) bool {
	want := strings.TrimLeft(name, `\`)
	resolved := n.Child("name").Name()

	return n.Kind() == "Attribute" && (resolved == want || ShortName(resolved) == want)
}
