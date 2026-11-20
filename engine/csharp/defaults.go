package csharp

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func blankDefault(match engine.Match) bool {
	n := Node{Match: match}
	return n.IsBlankStringDefault() && n.DefaultedNameTestedForBlankness()
}
func deNulledFinder(match engine.Match) bool {
	if match.Kind() != "MethodDeclaration" {
		return false
	}
	n := Node{Match: match}
	actual := n.DeclaredType()
	if n.IsInherited() || !actual.Exists() || !actual.IsNullable() || actual.IsValueType() || strings.TrimSuffix(actual.Name(), "?") == "global::System.String" {
		return false
	}
	calls := Of(match.Codebase()).CallsTo(n)
	return calls.OutsideTests >= 2 && calls.Asserting == calls.OutsideTests
}
func manufacturesFill(match engine.Match) bool {
	n := Node{Match: match}
	if match.Role() == "expression" && n.Fallback().IsEmptyScalar() && n.FillsArgument() && !n.AddsNothing() {
		return true
	}
	return n.RunsABody() && n.IsInventingOnMiss() && !Of(match.Codebase()).EveryCallerAccumulates(n)
}
func coalescedLoop(match engine.Match) bool {
	if match.Kind() != "ForEachStatement" {
		return false
	}
	expressions := (Node{Match: match}).Expressions()
	return len(expressions) > 0 && expressions[0].Is("CoalesceExpression") && expressions[0].Fallback().IsEmptyCollection()
}
