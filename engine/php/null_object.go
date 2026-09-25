package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// NullObjectFor is how a default builds the Null Object of a class, the class written as asWritten: `new X()` when
// its constructor needs no argument, else its one public static factory returning a constant `new self(...)`,
// inlined; nothing when the class has no expressible Null Object.
func NullObjectFor(codebase *engine.Codebase, fqcn, asWritten string) (string, bool) {
	class, declared := ProgramOf(codebase).Class(fqcn)
	if declared && constructorRequiresNoArguments(class) {
		return "new " + asWritten + "()", true
	}
	if !declared || class.Kind() != "Stmt_Class" {
		return "", false
	}
	var factories []engine.Match
	for _, method := range Methods(class) {
		if returned, ok := nullObjectReturn(method, class); ok {
			factories = append(factories, returned)
		}
	}
	if len(factories) != 1 {
		return "", false
	}
	printed, err := Printed(factories[0], func(name engine.Match) (string, bool) {
		return asWritten, isSelfName(name)
	})

	return printed, err == nil
}

// constructorRequiresNoArguments says whether a class can be built with no argument: no constructor, or one whose
// every parameter has a default or is variadic.
func constructorRequiresNoArguments(class engine.Match) bool {
	if class.Kind() != "Stmt_Class" {
		return false
	}
	if _, hasConstructor := Method(class, "__construct"); !hasConstructor {
		return true
	}
	for _, param := range ConstructorParams(class) {
		if isRequiredParam(param) {
			return false
		}
	}

	return true
}

// nullObjectReturn is what a public static factory with no required parameter returns in its one statement, when
// that is a constant construction of its own class.
func nullObjectReturn(method, class engine.Match) (engine.Match, bool) {
	modifiers := method.Node().Modifiers
	public := slices.Contains(modifiers, "public") || !slices.Contains(modifiers, "protected") && !slices.Contains(modifiers, "private")
	if !slices.Contains(modifiers, "static") || !public {
		return engine.Match{}, false
	}
	for _, param := range Params(method) {
		if isRequiredParam(param) {
			return engine.Match{}, false
		}
	}
	statements := method.ChildrenIn("stmts")
	if len(statements) != 1 || statements[0].Kind() != "Stmt_Return" || !statements[0].Child("expr").Exists() {
		return engine.Match{}, false
	}
	returned := statements[0].Child("expr")

	return returned, isSelfConstruction(returned) && isInlinableConstant(returned, class)
}

// isRequiredParam says whether a caller must pass the parameter: it has no default and is not variadic.
func isRequiredParam(param engine.Match) bool {
	return !param.Child("default").Exists() && !slices.Contains(param.Node().Flags, "variadic")
}

// isSelfConstruction says whether an expression is `new self(...)` or `new static(...)`.
func isSelfConstruction(expression engine.Match) bool {
	return expression.Kind() == "Expr_New" && isSelfName(expression.Child("class"))
}

func isSelfName(name engine.Match) bool {
	lower := strings.ToLower(name.Name())

	return isName(name) && (lower == "self" || lower == "static")
}

// isInlinableConstant says whether an expression means the same written anywhere: a literal, a constant, its
// negation, a public constant of the class, or a construction of the class from those.
func isInlinableConstant(expression, class engine.Match) bool {
	switch kind := expression.Kind(); {
	case strings.HasPrefix(kind, "Scalar_"):
		return true
	case kind == "Expr_ConstFetch":
		return isName(expression.Child("name"))
	case kind == "Expr_UnaryMinus":
		return isInlinableConstant(expression.Child("expr"), class)
	case kind == "Expr_ClassConstFetch":
		owner, constant := expression.Child("class"), expression.Child("name")

		return isName(owner) && strings.ToLower(owner.Name()) == "self" && constant.Kind() == "Identifier" && declaresPublicConst(class, constant.Name())
	case isSelfConstruction(expression):
		for _, argument := range expression.ChildrenIn("args") {
			if argument.Kind() != "Arg" || slices.Contains(argument.Node().Flags, "spread") || !isInlinableConstant(argument.Child("value"), class) {
				return false
			}
		}

		return true
	}

	return false
}

// declaresPublicConst says whether the class declares the constant neither private nor protected.
func declaresPublicConst(class engine.Match, name string) bool {
	for _, group := range class.ChildrenIn("stmts") {
		modifiers := group.Node().Modifiers
		if group.Kind() != "Stmt_ClassConst" || slices.Contains(modifiers, "private") || slices.Contains(modifiers, "protected") {
			continue
		}
		for _, constant := range group.ChildrenIn("consts") {
			if constant.Child("name").Name() == name {
				return true
			}
		}
	}

	return false
}
