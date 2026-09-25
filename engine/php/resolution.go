package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// UnpacksTargetFromContainerParam says whether the node declares a method taking an object and a string or int key,
// that looks the key up on the object — $workflow->graph->nodeById($id) — into a local, and uses the object for
// nothing but reading its properties: the method wants the looked-up object, not the pair.
func (n Node) UnpacksTargetFromContainerParam() bool {
	if n.Kind() != "Stmt_ClassMethod" {
		return false
	}
	owners, keys := n.objectParams(), n.scalarKeyParams()
	if len(owners) == 0 || len(keys) == 0 {
		return false
	}
	for _, statement := range n.ChildrenIn("stmts") {
		for _, call := range withDescendants(statement) {
			if call.Kind() != "Expr_MethodCall" || !keyedSolelyBy(call, keys) || !slices.Contains(owners, variableName(chainRoot(call.Child("var")))) {
				continue
			}
			local := capturedLocal(call)
			if local == "" || n.isTheResolver(local) {
				continue
			}
			if n.ownerIsPureEncapsulator(chainRoot(call.Child("var"))) {
				return true
			}
		}
	}

	return false
}

// baseType is a parameter's type with a nullable ? taken off; none for a union or an intersection.
func baseType(written *contract.Type) *contract.Type {
	if written == nil || written.Kind == "union" || written.Kind == "intersection" {
		return nil
	}

	return written
}

func (n Node) objectParams() []string {
	var names []string
	for _, param := range Params(n.Match) {
		written, variable := baseType(param.Node().Declared), param.Child("var")
		if variable.Kind() == "Expr_Variable" && variable.Name() != "" && written != nil && written.Kind == "named" &&
			!strings.HasPrefix(ShortName(written.Name), "Reflection") && !ProgramOf(n.Codebase()).IsEnum(written.Name) {
			names = append(names, variable.Name())
		}
	}

	return names
}

func (n Node) scalarKeyParams() []string {
	var names []string
	for _, param := range Params(n.Match) {
		written, variable := baseType(param.Node().Declared), param.Child("var")
		if variable.Kind() == "Expr_Variable" && variable.Name() != "" && written != nil && written.Kind == "keyword" &&
			(written.Name == "string" || written.Name == "int") {
			names = append(names, variable.Name())
		}
	}

	return names
}

// keyedSolelyBy says whether the call passes exactly one argument, a key parameter.
func keyedSolelyBy(call engine.Match, keys []string) bool {
	var passed []engine.Match
	for _, child := range call.Children() {
		if child.Node().Field == "args" {
			passed = append(passed, child)
		}
	}

	return len(passed) == 1 && passed[0].Kind() == "Arg" && slices.Contains(keys, variableName(passed[0].Child("value")))
}

// chainRoot is what a chain of sends and property reads starts at.
func chainRoot(node engine.Match) engine.Match {
	for {
		switch node.Kind() {
		case "Expr_MethodCall", "Expr_PropertyFetch", "Expr_NullsafeMethodCall", "Expr_NullsafePropertyFetch":
			node = node.Child("var")
		default:
			return node
		}
	}
}

// capturedLocal is the variable the call's result, sends chained onto it included, is assigned to.
func capturedLocal(call engine.Match) string {
	node, parent := call, call.Parent()
	for isMethodSend(parent) && node.Node().Field == "var" {
		node, parent = parent, parent.Parent()
	}
	if parent.Kind() == "Expr_Assign" && node.Node().Field == "expr" {
		return variableName(parent.Child("var"))
	}

	return ""
}

// isTheResolver says whether the method only guards and then returns the local: it is the lookup itself.
func (n Node) isTheResolver(local string) bool {
	body := n.ChildrenIn("stmts")
	if len(body) < 2 || body[len(body)-1].Kind() != "Stmt_Return" || variableName(body[len(body)-1].Child("expr")) != local {
		return false
	}
	for _, between := range body[1 : len(body)-1] {
		guard := Node{Match: between}
		if between.Kind() != "Stmt_If" || guard.Child("else").Exists() || len(guard.ChildrenIn("elseifs")) > 0 {
			return false
		}
		if slices.ContainsFunc(guard.ChildrenIn("stmts"), func(statement engine.Match) bool {
			return !(Node{Match: statement}).IsBailOut() || statement.Kind() != "Stmt_Expression"
		}) {
			return false
		}
	}

	return true
}

// ownerIsPureEncapsulator says whether every other use of the object parameter in the method reads a property of it.
func (n Node) ownerIsPureEncapsulator(root engine.Match) bool {
	owner := root.Name()
	for _, statement := range n.ChildrenIn("stmts") {
		for _, variable := range withDescendants(statement) {
			if variable.Kind() != "Expr_Variable" || variable.Name() != owner || variable.Node() == root.Node() {
				continue
			}
			if !isPropertyRead(variable.Parent()) || variable.Node().Field != "var" {
				return false
			}
		}
	}

	return true
}
