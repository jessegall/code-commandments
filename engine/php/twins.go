package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func TwinUnits(codebase *engine.Codebase) []engine.ReachedUnit {
	return units.Of(codebase)
}

var units = engine.Memoised(twinUnits)

func Divergences(codebase *engine.Codebase) []engine.Divergence {
	return divergences.Of(codebase)
}

var divergences = engine.Memoised(func(codebase *engine.Codebase) []engine.Divergence {
	return engine.DivergentTwins(Twins(codebase), TwinUnits(codebase))
})

func IsDivergentTwin(method engine.Match) bool {
	return engine.PairOf(Divergences(method.Codebase()), ScopeOf(method)) != ""
}

func twinUnits(codebase *engine.Codebase) []engine.ReachedUnit {
	scopes := ReachOf(codebase).Scopes
	var units []engine.ReachedUnit
	for _, method := range In(codebase).WhereKind("Stmt_ClassMethod").Get() {
		if len(method.ChildrenIn("stmts")) == 0 {
			continue
		}
		key := ScopeOf(method)
		if steps := scopes.RareOf(key, engine.TwinMaxShare); len(steps) >= engine.TwinMinShared {
			units = append(units, engine.ReachedUnit{Key: key, Match: method, Resources: steps})
		}
	}

	return units
}

// TwinJudge is what PHP answers for the twin reading.
type TwinJudge struct {
	codebase *engine.Codebase
}

func Twins(codebase *engine.Codebase) TwinJudge {
	return TwinJudge{codebase: codebase}
}

func (j TwinJudge) IsType(resource string) bool {
	return ReachOf(j.codebase).IsType(resource)
}

func (j TwinJudge) ArePolymorphicSiblings(poorer, richer engine.ReachedUnit) bool {
	method := Node{Match: poorer.Match}.MethodName()
	if method != (Node{Match: richer.Match}).MethodName() {
		return false
	}
	program := ProgramOf(j.codebase)

	return program.OverridesMethod(EnclosingClassName(poorer.Match), method) && program.OverridesMethod(EnclosingClassName(richer.Match), method)
}

func (j TwinJudge) ResultsAreIncomparable(poorer, richer engine.ReachedUnit) bool {
	one, other := Node{Match: poorer.Match}.ReturnTypeName(), Node{Match: richer.Match}.ReturnTypeName()
	if one == "" || other == "" || one == other {
		return false
	}
	program := ProgramOf(j.codebase)
	_, oneIsObject := program.Declaration(one)
	_, otherIsObject := program.Declaration(other)
	if oneIsObject != otherIsObject {
		return true
	}
	if oneIsObject {
		return !program.IsA(one, other) && !program.IsA(other, one)
	}

	return !Overlaps(one, other)
}

var methods = engine.Memoised(func(codebase *engine.Codebase) map[string]engine.Match {
	byScope := map[string]engine.Match{}
	for _, method := range In(codebase).WhereKind("Stmt_ClassMethod").Get() {
		byScope[ScopeOf(method)] = method
	}

	return byScope
})

func (j TwinJudge) CallersOf(key string) []string {
	method, found := methods.Of(j.codebase)[key]
	if !found {
		return nil
	}
	class, name := EnclosingClassName(method), Node{Match: method}.MethodName()
	if class == "" || name == "" {
		return nil
	}
	var callers []string
	for _, call := range IndexOf(j.codebase).CallersOf(class, name) {
		if scope := ScopeOf(call); !slices.Contains(callers, scope) {
			callers = append(callers, scope)
		}
	}

	return callers
}
