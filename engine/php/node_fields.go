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
		triples = append(triples, tripledWith(directFieldsAndReaches(expression))...)
	}

	return triples
}

// tripledWith pairs each property the expression reads directly with each [base, field] it reaches through
// another one: the triple a reader of the rule needs, which is the own property, the property reached through,
// and the field taken off it. A direct read of the very property being reached through is no triple, since
// there is nothing being reached past.
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

// IsUnreadCollaborator says whether the node is a promoted constructor parameter the class keeps to itself,
// takes a collaborator through, and then never reads: the class asks for something it does not use, and the
// only way a reader learns that is to search the whole type. A class that could read a field without naming it
// answers no, since the read may well be somewhere this cannot see.
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

// constructorUsesTheArgument says whether the constructor's own body reads the promoted parameter as the plain
// argument it also is. The field is still read nowhere, but the value is used, so the fix is to stop promoting
// it rather than to take it away, and that is a different thing to tell someone.
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

// ReadsItsFieldsWithoutNamingThem says whether the class could read a field of its own without writing its name:
// it uses a trait, answers a read it does not declare, or hands the whole object somewhere that reads every
// field. A field's absence from the source then says nothing about whether it is read.
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

// readsTheWholeObject says whether the expression reads every field of the object at once: a dynamic property
// fetch, a cast of `$this` to an array, or a call that unpacks the whole object.
func readsTheWholeObject(node engine.Match) bool {
	if node.Kind() == "Expr_PropertyFetch" && node.Child("name").Kind() != "Identifier" {
		return true
	}
	if node.Kind() == "Expr_Cast_Array" && node.Child("expr").Kind() == "Expr_Variable" && node.Child("expr").Name() == "this" {
		return true
	}

	return node.Kind() == "Expr_FuncCall" && slices.Contains([]string{"compact", "get_object_vars", "serialize"}, node.Child("name").Name())
}

// NamesTheField says whether anything in the class names the field, the constructor's own promotion aside: a
// read, a write, or an interpolation of `$this->name`.
func (n Node) NamesTheField(name string) bool {
	return slices.ContainsFunc(descendantsOf(n.Match), func(node engine.Match) bool {
		fetch := Node{Match: node}

		return fetch.isSelfFieldNamed(name)
	})
}

// isSelfFieldNamed says whether the node reads or writes `$this-><name>`.
func (n Node) isSelfFieldNamed(name string) bool {
	if !slices.Contains([]string{"Expr_PropertyFetch", "Expr_NullsafePropertyFetch"}, n.Kind()) {
		return false
	}
	owner := n.Child("var")

	return owner.Kind() == "Expr_Variable" && owner.Name() == "this" && n.Child("name").Name() == name
}

// FilledParameter is the parameter a function fills and never gives back, and whether it fills one: a parameter
// typed as a class whose own property the body writes, in a function that returns nothing. The signature then
// says the object goes in and says nothing about it coming out changed, so the caller has to know.
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

// AsParameterField is the name a parameter typed as a class goes by, and whether it is one: a promoted one is
// the class's own field and no part of this, and a scalar or an array carries no property to write.
func AsParameterField(parameter engine.Match) (string, bool) {
	variable := parameter.Child("var")
	if slices.Contains(parameter.Node().Flags, "promoted") || variable.Kind() != "Expr_Variable" || variable.Name() == "" {
		return "", false
	}

	return variable.Name(), Written(parameter.Node().Declared).Class() != ""
}

// writesAPropertyOf says whether the function writes a property of the variable the name holds.
func (n Node) writesAPropertyOf(name string) bool {
	return slices.ContainsFunc(descendantsOf(n.Match), func(node engine.Match) bool {
		return Node{Match: node}.isPropertyWriteOn(name)
	})
}

// isPropertyWriteOn says whether the node writes a property of the variable the name holds: `$order->total = …`.
func (n Node) isPropertyWriteOn(name string) bool {
	target := n.Child("var")
	if n.Kind() != "Expr_Assign" || target.Kind() != "Expr_PropertyFetch" {
		return false
	}
	owner := target.Child("var")

	return owner.Kind() == "Expr_Variable" && owner.Name() == name
}
