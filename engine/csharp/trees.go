package csharp

import (
	"slices"
	"strings"
)

// expressionTree is the type a lambda fills when the compiler is to build a TREE of it rather than a function: the
// lambda's body is never run, it is read — translated to SQL, to a query, to a column name — by whatever was handed
// it.
const expressionTree = "global::System.Linq.Expressions.Expression<"

// SitsInAnExpressionTree says whether the node stands inside a lambda the compiler builds a tree of. Nothing in
// such a lambda dereferences anything, so a `!` in it silences the conversion of a nullable member to `object` and
// forgives no null; a report of exactly that shape is what this reading answers.
func (n Node) SitsInAnExpressionTree() bool {
	for held := n; held.Exists(); held = held.Parent() {
		if !held.Is("SimpleLambdaExpression", "ParenthesizedLambdaExpression") {
			continue
		}
		if isTreeTyped(held.Parent()) || fillsATreeParameter(held) {
			return true
		}
	}

	return false
}

// isTreeTyped says whether the lambda is held by something written as a tree: a variable, a field or a property
// declared `Expression<...>`.
func isTreeTyped(holder Node) bool {
	for held := holder; held.Exists(); held = held.Parent() {
		if !held.Is("VariableDeclarator", "VariableDeclaration", "PropertyDeclaration", "FieldDeclaration") {
			continue
		}
		if isATree(held.Type().Name()) {
			return true
		}
		for _, written := range held.All() {
			if written.IsTypeNode() && isATree(written.Type().Name()) {
				return true
			}
		}
	}

	return false
}

// fillsATreeParameter says whether the lambda is the argument of a call that takes a tree where the lambda
// stands, and says so too where the call is one the compiler never bound.
//
// A call goes unbound when the compilation has no reference for what it names: a package that is not restored, a
// project that did not build. The lambda may well be a tree then — `RuleFor(x => x.Logo!.Path)` of a validation
// library is one — and nothing here can tell. A rule may not report what the engine could not read, so an unbound
// call withholds the finding rather than asserting the lambda runs.
//
// A call that names its arguments is the same story one step smaller: the compiler gives the parameters by
// position and not by name, so which one a named argument fills cannot be read here. A target that declares a
// tree parameter at all therefore takes the lambda as filling it.
func fillsATreeParameter(lambda Node) bool {
	argument := lambda.Parent()
	if !argument.Is("Argument") {
		return false
	}

	list := argument.Parent()
	call := list.Parent()
	if !call.Target().Exists() {
		return true
	}

	parameters := call.Target().Parameters()
	if !call.PassesByPosition() {
		return slices.ContainsFunc(parameters, isATree)
	}

	return isATree(parameterAt(parameters, positionIn(list, argument)))
}

// positionIn is where the argument stands among the call's arguments, -1 for one that is not among them.
func positionIn(list Node, argument Node) int {
	return slices.IndexFunc(list.All(), func(each Node) bool { return each.Node() == argument.Node() })
}

// parameterAt is the parameter the argument at the position fills: the one declared there, or the last one where
// the position runs past them and that last one is a `params` array, since such a parameter takes every argument
// from its own position on — `UniqueIndex(x => x.A!, x => x.B!)` of a document store fills one parameter twice.
func parameterAt(parameters []string, position int) string {
	if position < 0 || len(parameters) == 0 {
		return ""
	}
	if position < len(parameters) {
		return parameters[position]
	}

	last := parameters[len(parameters)-1]
	if !strings.HasSuffix(last, "[]") {
		return ""
	}

	return last
}

// isATree says whether the parameter is written as a tree of a lambda rather than a function, an array of them
// included, which is how a `params Expression<...>[]` is declared.
func isATree(parameter string) bool {
	return strings.HasPrefix(parameter, expressionTree)
}
