package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// TwinUnits is every method with a body that reaches enough rare resources to be compared, keyed by its scope.
func TwinUnits(codebase *engine.Codebase) []engine.ReachedUnit {
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

// Twins is the PHP judge of the codebase.
func Twins(codebase *engine.Codebase) TwinJudge {
	return TwinJudge{codebase: codebase}
}

// IsType says whether a resource is a class rather than a function call.
func (j TwinJudge) IsType(resource string) bool {
	return ReachOf(j.codebase).IsType(resource)
}

// ArePolymorphicSiblings says whether the two are one method answering a contract each inherits.
func (j TwinJudge) ArePolymorphicSiblings(poorer, richer engine.ReachedUnit) bool {
	method := Node{Match: poorer.Match}.MethodName()
	if method != (Node{Match: richer.Match}).MethodName() {
		return false
	}
	program := ProgramOf(j.codebase)

	return program.OverridesMethod(EnclosingClassName(poorer.Match), method) && program.OverridesMethod(EnclosingClassName(richer.Match), method)
}

// ResultsAreIncomparable says whether both declare a result and they cannot be one value: a class beside a builtin,
// two unrelated classes, or two builtins that do not overlap.
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

// CallersOf is the scope of every call of the unit's method.
func (j TwinJudge) CallersOf(unit engine.ReachedUnit) []string {
	class, method := EnclosingClassName(unit.Match), Node{Match: unit.Match}.MethodName()
	if class == "" || method == "" {
		return nil
	}
	var callers []string
	for _, call := range IndexOf(j.codebase).CallersOf(class, method) {
		if scope := ScopeOf(call); !slices.Contains(callers, scope) {
			callers = append(callers, scope)
		}
	}

	return callers
}
