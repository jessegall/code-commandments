package php

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// ReceiverTypeOf is the class a method send's receiver is declared as, read only from what the enclosing scope
// writes: `$this`, a parameter's type, or `$this->field`'s declared type. Empty for anything else.
func ReceiverTypeOf(call engine.Match) string {
	if call.Kind() != "Expr_MethodCall" && call.Kind() != "Expr_NullsafeMethodCall" {
		return ""
	}
	receiver := call.Child("var")
	switch {
	case receiver.Kind() == "Expr_Variable" && receiver.Name() == "this":
		return enclosingClassName(call)
	case receiver.Kind() == "Expr_Variable":
		return receiverParamType(call, receiver.Name())
	case receiver.Kind() == "Expr_PropertyFetch" && receiver.Child("var").Kind() == "Expr_Variable" &&
		receiver.Child("var").Name() == "this" && receiver.Child("name").Kind() == "Identifier":
		return receiverPropertyType(call, receiver.Child("name").Name())
	}

	return ""
}

func receiverParamType(call engine.Match, variable string) string {
	function := enclosingFunction(call)
	if variable == "" || !function.Exists() {
		return ""
	}
	for _, param := range params(function) {
		if own := param.Child("var"); own.Kind() == "Expr_Variable" && own.Name() == variable {
			return writtenClass(param.Node().Declared)
		}
	}

	return ""
}

func receiverPropertyType(call engine.Match, name string) string {
	class := enclosingClass(call)
	if !class.Exists() {
		return ""
	}
	for _, param := range constructorParams(class) {
		if own := param.Child("var"); slices.Contains(param.Node().Flags, "promoted") && own.Kind() == "Expr_Variable" && own.Name() == name {
			return writtenClass(param.Node().Declared)
		}
	}
	for _, member := range class.Children() {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		for _, item := range member.Children() {
			if item.Node().Field == "props" && item.Name() == name {
				return writtenClass(member.Node().Declared)
			}
		}
	}

	return ""
}

// writtenClass is the one name a type is written with, a `?` aside: a class, or `self`, `static`, `parent`.
func writtenClass(declared *contract.Type) string {
	written := Written(declared)
	if written.isSugared() {
		written = Written(written.bare())
	}
	if written.isWrittenAsName() {
		return written.written.Name
	}

	return ""
}

// Callee is the class that declares what a call reaches, and the member's name: a construction's constructor, a
// static call's method, a method send's method on its receiver's declared type.
func (t *Types) Callee(call engine.Match) (owner, method string) {
	receiver := ""
	switch call.Kind() {
	case "Expr_New":
		if class := call.Child("class"); isName(class) {
			receiver, method = class.Name(), "__construct"
		}
	case "Expr_StaticCall":
		class, name := call.Child("class"), call.Child("name")
		if isName(class) {
			receiver = class.Name()
			if receiver == "self" || receiver == "static" {
				receiver = enclosingClassName(call)
			}
		}
		if name.Kind() == "Identifier" {
			method = name.Name()
		}
	default:
		receiver = ReceiverTypeOf(call)
		if name := call.Child("name"); (call.Kind() == "Expr_MethodCall" || call.Kind() == "Expr_NullsafeMethodCall") && name.Kind() == "Identifier" {
			method = name.Name()
		}
	}
	if method == "" {
		return "", ""
	}
	if owner = t.DeclaringClassOfMethod(receiver, method); owner == "" {
		return "", ""
	}

	return owner, method
}

// CallName is the member a call names: `__construct` for a construction.
func CallName(call engine.Match) string {
	switch call.Kind() {
	case "Expr_New":
		return "__construct"
	case "Expr_StaticCall", "Expr_MethodCall", "Expr_NullsafeMethodCall":
		if name := call.Child("name"); name.Kind() == "Identifier" {
			return name.Name()
		}
	}

	return ""
}

func (t *Types) fillTarget(call engine.Match) {
	if owner, method := t.Callee(call); owner != "" {
		call.Node().Target = &contract.Target{Symbol: owner + "::" + method + "()", Type: owner, Name: method}
	}
}

// enclosingClass is the nearest class-like around the node, or the node itself.
func enclosingClass(node engine.Match) engine.Match {
	for at := node; at.Exists(); at = at.Parent() {
		if slices.Contains(classLikes, at.Kind()) {
			return at
		}
	}

	return engine.Match{}
}

// Chains reads what a property or argumentless method chain returns, from the declared types of classes and enums
// alone: `$order->customer->address()`, given the types its variables hold.
type Chains struct {
	properties map[string]map[string]string
	returns    map[string]map[string]string
}

var chains sync.Map

// ChainsOf is the codebase's chain reader, indexed on first need.
func ChainsOf(codebase *engine.Codebase) *Chains {
	if read, ok := chains.Load(codebase); ok {
		return read.(*Chains)
	}
	read, _ := chains.LoadOrStore(codebase, indexChains(codebase))

	return read.(*Chains)
}

func indexChains(codebase *engine.Codebase) *Chains {
	read := &Chains{properties: map[string]map[string]string{}, returns: map[string]map[string]string{}}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, kind := range []string{"Stmt_Class", "Stmt_Enum"} {
			for _, node := range file.Nodes() {
				if node.Kind == kind && node.Symbol != "" {
					read.index(file.Match(node.ID))
				}
			}
		}
	}

	return read
}

func (c *Chains) index(declaration engine.Match) {
	properties, returns := map[string]string{}, map[string]string{}
	for _, member := range declaration.Children() {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		if class := writtenClass(member.Node().Declared); class != "" {
			for _, item := range member.Children() {
				if item.Node().Field == "props" {
					properties[item.Name()] = strings.TrimLeft(class, `\`)
				}
			}
		}
	}
	for _, param := range constructorParams(declaration) {
		own := param.Child("var")
		if class := writtenClass(param.Node().Declared); slices.Contains(param.Node().Flags, "promoted") && own.Kind() == "Expr_Variable" && own.Name() != "" && class != "" {
			properties[own.Name()] = strings.TrimLeft(class, `\`)
		}
	}
	for _, method := range Methods(declaration) {
		if class := writtenClass(method.Node().Returns); class != "" {
			returns[strings.ToLower(method.Name())] = strings.TrimLeft(class, `\`)
		}
	}
	c.properties[declaration.Node().Symbol] = properties
	c.returns[declaration.Node().Symbol] = returns
}

// Resolve is the class the chain ends on, its variables typed by the map.
func (c *Chains) Resolve(expr engine.Match, variables map[string]string) string {
	switch expr.Kind() {
	case "Expr_Variable":
		return variables[expr.Name()]
	case "Expr_PropertyFetch", "Expr_NullsafePropertyFetch":
		base, name := strings.TrimLeft(c.Resolve(expr.Child("var"), variables), `\`), expr.Child("name")
		if base == "" || name.Kind() != "Identifier" {
			return ""
		}

		return c.properties[base][name.Name()]
	case "Expr_MethodCall", "Expr_NullsafeMethodCall":
		for _, arg := range expr.Children() {
			if arg.Node().Field == "args" {
				return ""
			}
		}
		base, name := strings.TrimLeft(c.Resolve(expr.Child("var"), variables), `\`), expr.Child("name")
		if base == "" || name.Kind() != "Identifier" {
			return ""
		}

		return c.returns[base][strings.ToLower(name.Name())]
	}

	return ""
}

// ParamTypes is each parameter of a function-like by its variable, with the one name its type is written with.
func ParamTypes(function engine.Match) map[string]string {
	types := map[string]string{}
	for _, param := range params(function) {
		own := param.Child("var")
		if written := Written(param.Node().Declared).SimpleName(); written != "" && own.Kind() == "Expr_Variable" && own.Name() != "" {
			types[own.Name()] = written
		}
	}

	return types
}
