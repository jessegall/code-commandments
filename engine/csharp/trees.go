package csharp

import "strings"

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
		if strings.HasPrefix(held.Type().Name(), expressionTree) {
			return true
		}
		for _, written := range held.All() {
			if written.IsTypeNode() && strings.HasPrefix(written.Type().Name(), expressionTree) {
				return true
			}
		}
	}

	return false
}

// fillsATreeParameter says whether the lambda is the argument of a call whose parameter at that position is a
// tree, and says so too where the call is one the compiler never bound.
//
// A call goes unbound when the compilation has no reference for what it names: a package that is not restored, a
// project that did not build. The lambda may well be a tree then — `RuleFor(x => x.Logo!.Path)` of a validation
// library is one — and nothing here can tell. A rule may not report what the engine could not read, so an unbound
// call withholds the finding rather than asserting the lambda runs.
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

	if !call.PassesByPosition() {
		return false
	}

	parameters := call.Target().Parameters()
	for position, each := range list.All() {
		if each.Node() != argument.Node() {
			continue
		}

		return position < len(parameters) && strings.HasPrefix(parameters[position], expressionTree)
	}

	return false
}
