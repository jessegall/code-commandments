package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// SelfPropertyGroupsAssembled is, for every new and array literal under the node, the own properties it is handed
// directly, where there are two or more.
func (n Node) SelfPropertyGroupsAssembled() [][]string {
	var groups [][]string
	for _, expression := range withDescendants(n.Match) {
		if expression.Kind() != "Expr_New" && expression.Kind() != "Expr_Array" {
			continue
		}
		var fields []string
		for _, value := range directArgumentValues(expression) {
			if name := selfPropertyOf(value); name != "" && !slices.Contains(fields, name) {
				fields = append(fields, name)
			}
		}
		if len(fields) >= 2 {
			groups = append(groups, fields)
		}
	}

	return groups
}

// directArgumentValues is the values an array literal holds or a construction or call passes, in order.
func directArgumentValues(node engine.Match) []engine.Match {
	var values []engine.Match
	switch node.Kind() {
	case "Expr_Array":
		for _, item := range (Node{Match: node}).In("items") {
			values = append(values, item.Child("value"))
		}
	case "Expr_New", "Expr_MethodCall", "Expr_StaticCall", "Expr_FuncCall":
		for _, argument := range Arguments(node) {
			values = append(values, argument.Child("value"))
		}
	}

	return values
}

// SelfPropertiesTestedForAbsence is every own property compared identically with null under the node, or tested
// for an instance of the optional class when one is named.
func (n Node) SelfPropertiesTestedForAbsence(optional string) []string {
	var tested []string
	add := func(name string) {
		if name != "" && !slices.Contains(tested, name) {
			tested = append(tested, name)
		}
	}
	for _, node := range withDescendants(n.Match) {
		switch node.Kind() {
		case "Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical":
			left, right := node.Child("left"), node.Child("right")
			if isNullFetch(right) {
				add(selfPropertyOf(left))
			}
			if isNullFetch(left) {
				add(selfPropertyOf(right))
			}
		case "Expr_Instanceof":
			class := node.Child("class")
			if optional != "" && isName(class) && strings.TrimLeft(class.Name(), `\`) == strings.TrimLeft(optional, `\`) {
				add(selfPropertyOf(node.Child("expr")))
			}
		}
	}

	return tested
}

// SelfFieldNestedReachTriples is, for every construction, array or call under the node, each own property it uses
// directly paired with each field it reaches through another own property: [direct, base, reached].
func (n Node) SelfFieldNestedReachTriples() [][3]string {
	var triples [][3]string
	for _, expression := range withDescendants(n.Match) {
		switch expression.Kind() {
		case "Expr_New", "Expr_Array", "Expr_MethodCall", "Expr_StaticCall", "Expr_FuncCall":
		default:
			continue
		}
		directs, reaches := directFieldsAndReaches(expression)
		for _, reach := range reaches {
			for _, direct := range directs {
				if direct != reach[0] {
					triples = append(triples, [3]string{direct, reach[0], reach[1]})
				}
			}
		}
	}

	return triples
}

// directFieldsAndReaches is the own properties an expression reads, and the [base, field] pairs it reaches through
// one of them.
func directFieldsAndReaches(expression engine.Match) ([]string, [][2]string) {
	var directs []string
	var reaches [][2]string
	for _, fetch := range withDescendants(expression) {
		if fetch.Kind() != "Expr_PropertyFetch" {
			continue
		}
		parent, base := fetch.Parent(), selfPropertyOf(fetch)
		if parent.Kind() == "Expr_PropertyFetch" && fetch.Node().Field == "var" && base != "" && parent.Child("name").Kind() == "Identifier" {
			reaches = append(reaches, [2]string{base, parent.Child("name").Name()})
		} else if base != "" && !slices.Contains(directs, base) {
			directs = append(directs, base)
		}
	}

	return directs, reaches
}

// RewritesSelfPropertyOutsideConstructor says whether a method of the class around the node, the constructor
// aside, assigns the named own property.
func (n Node) RewritesSelfPropertyOutsideConstructor(name string) bool {
	for _, method := range Methods(n.EnclosingClassLike().Match) {
		if method.Name() == "__construct" {
			continue
		}
		for _, node := range withDescendants(method) {
			if node.Kind() == "Expr_Assign" && selfPropertyOf(node.Child("var")) == name {
				return true
			}
		}
	}

	return false
}

// withDescendants is the node and every node under it, in pre-order.
func withDescendants(node engine.Match) []engine.Match {
	if !node.Exists() {
		return nil
	}

	return append([]engine.Match{node}, descendantsOf(node)...)
}

// PublicFieldNames is the name of every public field the node declares, when it is a class.
func (n Node) PublicFieldNames() []string {
	if n.Kind() != "Stmt_Class" {
		return nil
	}
	var names []string
	for _, field := range Fields(n.Match) {
		if field.IsPublic && !slices.Contains(names, field.Name) {
			names = append(names, field.Name)
		}
	}

	return names
}
