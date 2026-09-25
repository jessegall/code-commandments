package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// IsEnclosedInClass says whether the node sits in a class-like.
func (n Node) IsEnclosedInClass() bool {
	return n.EnclosingClassLike().Exists()
}

// firstPassed is the first argument a function call passes, placeholders included; no node when it passes none.
func (n Node) firstPassed() engine.Match {
	passed := n.ChildrenIn("args")
	if n.Kind() != "Expr_FuncCall" || len(passed) == 0 || passed[0].Kind() != "Arg" {
		return engine.Match{}
	}

	return passed[0]
}

// FirstArgIsClassLiteral says whether a function call's first argument is a class constant or a string.
func (n Node) FirstArgIsClassLiteral() bool {
	value := n.firstPassed().Child("value")

	return value.Kind() == "Expr_ClassConstFetch" || value.Kind() == "Scalar_String"
}

// FirstArgClassLiteral is the class a function call's first argument names, as a string or a class constant, self
// and static read as the class around it.
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

// IsEnclosingClassResolution says whether the call resolves the very class it is written in.
func (n Node) IsEnclosingClassResolution() bool {
	enclosing := EnclosingClassName(n.Match)

	return enclosing != "" && n.FirstArgClassLiteral() == enclosing
}

// StringArgument is the string literal passed at the position; empty when none is.
func (n Node) StringArgument(position int) (string, bool) {
	value := n.Argument(position)
	if value.Kind() != "Scalar_String" {
		return "", false
	}

	return value.Text()
}

// ArgumentCount is how many arguments the call passes.
func (n Node) ArgumentCount() int {
	return len(Arguments(n.Match))
}

// IsReCoercedToString says whether the node's value is cast to a string again, or sent toString().
func (n Node) IsReCoercedToString() bool {
	parent := n.Parent()
	if name := parent.Child("name"); parent.Kind() == "Expr_MethodCall" && n.Node().Field == "var" && name.Kind() == "Identifier" && name.Name() == "toString" {
		return true
	}

	return parent.Kind() == "Expr_Cast_String" && n.Node().Field == "expr"
}

// ReceiverMutatedNearby says whether the variable a method is sent to has a property written in its function.
func (n Node) ReceiverMutatedNearby() bool {
	receiver := n.Child("var")
	if receiver.Kind() != "Expr_Variable" || receiver.Name() == "this" || receiver.Name() == "" {
		return false
	}

	return slices.ContainsFunc(Trace(receiver), func(interaction Interaction) bool { return interaction.Kind == PropertyWrite })
}

// IsUsedOn says whether the method is sent to a receiver of one of the classes, from code outside them.
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

// IsEverProduced says whether the codebase ever builds the class or a subclass with new, or, when static factories
// are named, calls one of them on it.
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
