package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) HandRollsAKeyedStore() bool {
	if n.Kind() != "Stmt_Class" || n.HasModifier("abstract") {
		return false
	}
	for _, property := range n.arrayPropertyNames() {
		if n.storesUnderAHandedKey(property) && n.throwsOnAMissingKey(property) {
			return true
		}
	}

	return false
}

func (n Node) arrayPropertyNames() []string {
	var names []string
	for _, member := range n.Children() {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		for _, item := range member.Children() {
			if item.Node().Field == "props" {
				names = append(names, item.Name())
			}
		}
	}
	for _, param := range ConstructorParams(n.Match) {
		if slices.Contains(param.Node().Flags, "promoted") && param.Child("var").Kind() == "Expr_Variable" {
			names = append(names, param.Child("var").Name())
		}
	}

	return names
}

func keyedRead(node engine.Match, property string) bool {
	return node.Kind() == "Expr_ArrayDimFetch" && node.Child("dim").Exists() && memberOnThis(node.Child("var")) == property
}

func (n Node) storesUnderAHandedKey(property string) bool {
	for _, method := range Methods(n.Match) {
		for _, node := range withDescendants(method) {
			target := node.Child("var")
			if node.Kind() == "Expr_Assign" && keyedRead(target, property) && !isBareValue(target.Child("dim")) {
				return true
			}
		}
	}

	return false
}

func (n Node) throwsOnAMissingKey(property string) bool {
	for _, method := range Methods(n.Match) {
		for _, node := range withDescendants(method) {
			if node.Kind() == "Expr_BinaryOp_Coalesce" && keyedRead(node.Child("left"), property) && node.Child("right").Kind() == "Expr_Throw" {
				return true
			}
			if node.Kind() == "Stmt_If" && node.OnlyThrows() && testsKeyAbsence(node.Child("cond"), property) {
				return true
			}
		}
	}

	return false
}

func testsKeyAbsence(condition engine.Match, property string) bool {
	return slices.ContainsFunc(withDescendants(condition), func(negation engine.Match) bool {
		return negation.Kind() == "Expr_BooleanNot" && testsKeyPresence(negation.Child("expr"), property)
	})
}

func testsKeyPresence(node engine.Match, property string) bool {
	if node.Kind() == "Expr_Isset" {
		return slices.ContainsFunc(node.ChildrenIn("vars"), func(checked engine.Match) bool { return keyedRead(checked, property) })
	}
	arguments := Arguments(node)

	return (Node{Match: node}).CallsFunction("array_key_exists") && len(arguments) == 2 && memberOnThis(arguments[1].Child("value")) == property
}
