package python

import (
	"github.com/jessegall/code-commandments/engine"
)

func blankDefault(match engine.Match) bool {
	if match.Kind() != "arg" && match.Kind() != "AnnAssign" {
		return false
	}
	n := Node{Match: match}
	if !n.IsBlankStringDefault() || !n.DefaultedNameTestedForBlankness() {
		return false
	}
	p := Of(match.Codebase())
	return !p.IsFieldOfDataBuiltClass(n) && !p.IsParameterOfADispatchedMethod(n) && !p.IsParameterEveryCallFills(n)
}
func deNulledFinder(match engine.Match) bool {
	return match.Is(engine.Function) && Of(match.Codebase()).IsDeNulledFinder(Node{Match: match})
}
func manufacturesFill(match engine.Match) bool {
	n := Node{Match: match}
	if fallback, found := n.Fallback(); found && fallback.IsEmptyScalar() && n.FillsArgument() && !n.IsKeyedDefault() && !n.FallsBackOnAGroupOfAMatch() {
		return true
	}
	return match.Is(engine.Return) && n.ReturnedValue().IsEmptyScalar() && n.EnclosingFunction().IsInventingOnMiss()
}
func coalescedLoop(match engine.Match) bool {
	n := Node{Match: match}
	return n.IsLoopSubject() && n.FallsBackToEmptyCollection() && n.FallbackReachesIntoParameter()
}
