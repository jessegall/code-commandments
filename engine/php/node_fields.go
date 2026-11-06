package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

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

func directArgumentValues(node engine.Match) []engine.Match {
	var values []engine.Match
	switch node.Kind() {
	case "Expr_Array":
		for _, item := range (Node{Match: node}).ChildrenIn("items") {
			values = append(values, item.Child("value"))
		}
	case "Expr_New", "Expr_MethodCall", "Expr_StaticCall", "Expr_FuncCall":
		for _, argument := range Arguments(node) {
			values = append(values, argument.Child("value"))
		}
	}

	return values
}

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

func (n Node) SelfFieldNestedReachTriples() [][3]string {
	var triples [][3]string
	for _, expression := range withDescendants(n.Match) {
		switch expression.Kind() {
		case "Expr_New", "Expr_Array", "Expr_MethodCall", "Expr_StaticCall", "Expr_FuncCall":
		default:
			continue
		}
		triples = append(triples, tripledWith(directFieldsAndReaches(expression))...)
	}

	return triples
}

func tripledWith(directs []string, reaches [][2]string) [][3]string {
	var triples [][3]string
	for _, reach := range reaches {
		for _, direct := range directs {
			if direct == reach[0] {
				continue
			}
			triples = append(triples, [3]string{direct, reach[0], reach[1]})
		}
	}

	return triples
}

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

func withDescendants(node engine.Match) []engine.Match {
	if !node.Exists() {
		return nil
	}

	return append([]engine.Match{node}, descendantsOf(node)...)
}

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

func (n Node) IsUnreadCollaborator() bool {
	field, isField := AsField(n.Match)
	class := Node{Match: n.Closest(engine.TypeDeclaration)}
	if !isField || !field.Promoted || !slices.Contains(n.Node().Modifiers, "private") {
		return false
	}
	if Written(field.Type).Class() == "" || len(AttributeNames(field.Declaration)) > 0 {
		return false
	}
	if class.ReadsItsFieldsWithoutNamingThem() {
		return false
	}
	if n.constructorUsesTheArgument(field.Name) {
		return false
	}

	return !class.NamesTheField(field.Name)
}

func (n Node) constructorUsesTheArgument(name string) bool {
	constructor := n.Up()
	for _, statement := range constructor.ChildrenIn("stmts") {
		for _, node := range append([]engine.Match{statement}, descendantsOf(statement)...) {
			if node.Kind() == "Expr_Variable" && node.Name() == name {
				return true
			}
		}
	}

	return false
}

func (n Node) ReadsItsFieldsWithoutNamingThem() bool {
	if len(traitNames(n.Match)) > 0 {
		return true
	}
	for _, method := range Methods(n.Match) {
		if slices.Contains([]string{"__get", "__set", "__call", "__serialize", "__sleep", "__debugInfo"}, method.Child("name").Name()) {
			return true
		}
	}

	return slices.ContainsFunc(descendantsOf(n.Match), readsTheWholeObject)
}

func readsTheWholeObject(node engine.Match) bool {
	if node.Kind() == "Expr_PropertyFetch" && node.Child("name").Kind() != "Identifier" {
		return true
	}
	if node.Kind() == "Expr_Cast_Array" && node.Child("expr").Kind() == "Expr_Variable" && node.Child("expr").Name() == "this" {
		return true
	}

	return node.Kind() == "Expr_FuncCall" && slices.Contains([]string{"compact", "get_object_vars", "serialize"}, node.Child("name").Name())
}

func (n Node) NamesTheField(name string) bool {
	return slices.ContainsFunc(descendantsOf(n.Match), func(node engine.Match) bool {
		fetch := Node{Match: node}

		return fetch.isSelfFieldNamed(name)
	})
}

func (n Node) isSelfFieldNamed(name string) bool {
	if !slices.Contains([]string{"Expr_PropertyFetch", "Expr_NullsafePropertyFetch"}, n.Kind()) {
		return false
	}
	owner := n.Child("var")

	return owner.Kind() == "Expr_Variable" && owner.Name() == "this" && n.Child("name").Name() == name
}

func (n Node) FilledParameter() (string, bool) {
	if !n.IsCommandMethod() && n.DeclaredReturnType() != "" {
		return "", false
	}
	for _, parameter := range Params(n.Match) {
		field, ok := AsParameterField(parameter)
		if !ok {
			continue
		}
		if n.writesAPropertyOf(field) {
			return field, true
		}
	}

	return "", false
}

func AsParameterField(parameter engine.Match) (string, bool) {
	variable := parameter.Child("var")
	if slices.Contains(parameter.Node().Flags, "promoted") || variable.Kind() != "Expr_Variable" || variable.Name() == "" {
		return "", false
	}

	return variable.Name(), Written(parameter.Node().Declared).Class() != ""
}

func (n Node) writesAPropertyOf(name string) bool {
	return slices.ContainsFunc(descendantsOf(n.Match), func(node engine.Match) bool {
		return Node{Match: node}.isPropertyWriteOn(name)
	})
}

func (n Node) isPropertyWriteOn(name string) bool {
	target := n.Child("var")
	if n.Kind() != "Expr_Assign" || target.Kind() != "Expr_PropertyFetch" {
		return false
	}
	owner := target.Child("var")

	return owner.Kind() == "Expr_Variable" && owner.Name() == name
}
