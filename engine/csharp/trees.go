package csharp

import (
	"slices"
	"strings"
)

const expressionTree = "global::System.Linq.Expressions.Expression<"

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

func positionIn(list Node, argument Node) int {
	return slices.IndexFunc(list.All(), func(each Node) bool { return each.Node() == argument.Node() })
}

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

func isATree(parameter string) bool {
	return strings.HasPrefix(parameter, expressionTree)
}
