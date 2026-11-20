package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func blankDefault(match engine.Match) bool {
	n := Node{Match: match}
	if !n.IsBlankString() || !n.IsDeclarationDefault() || Written(n.DeclaredType()).Render() != "string" {
		return false
	}

	return n.DefaultedNameTestedForBlankness() || n.DeclarationBecomesProperty() && match.HasFieldBlankReplacement(n.DeclaredName())
}

func blankReplacement(match engine.Match) (engine.Match, engine.Match) {
	if (Node{Match: match}).isShortTernary() {
		return match.Child("cond"), match.Child("else")
	}

	return engine.Match{}, engine.Match{}
}
func deNulledFinder(match engine.Match) bool {
	n := Node{Match: match}
	if match.Kind() != "Stmt_ClassMethod" || !n.ReturnsNullableObject() {
		return false
	}
	class, method := EnclosingClassName(match), EnclosingFunctionName(match)
	if class == "" || method == "" {
		return false
	}
	callers := IndexOf(match.Codebase()).CallersOf(class, method)
	if len(callers) < 2 {
		return false
	}
	for _, caller := range callers {
		if !(Node{Match: caller}).ResultIsDeNulled() {
			return false
		}
	}
	return true
}
func manufacturesFill(match engine.Match) bool {
	n := Node{Match: match}
	if !n.IsCoalesce() || !n.CoalesceRight().IsEmptyLiteral() || n.CoalesceRight().IsEmptyArrayLiteral() || !n.FillsArgument() {
		return false
	}
	argument := match.Parent()
	for strings.HasPrefix(argument.Kind(), "Expr_Cast_") {
		argument = argument.Parent()
	}
	return !ParameterFilled(match.Codebase(), argument).Child("default").SameSyntax(match.Child("right"))
}
func coalescedLoop(match engine.Match) bool {
	n := Node{Match: match}
	return n.IsLoopSubject() && n.FallsBackToEmptyCollection() && n.FallbackSubject().ReachesIntoParameter()
}
