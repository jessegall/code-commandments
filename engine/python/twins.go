package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// TwinUnits is every def that reaches enough rare resources to be compared, keyed where it is declared.
func (p *Program) TwinUnits() []engine.ReachedUnit {
	population := p.FunctionReach()
	var units []engine.ReachedUnit
	for _, module := range p.modules {
		for _, def := range module.Nodes() {
			if !def.IsFunction() {
				continue
			}
			key := DeclarationOf(def)
			if steps := population.RareOf(key, engine.TwinMaxShare); len(steps) >= engine.TwinMinShared {
				units = append(units, engine.ReachedUnit{Key: key, Match: def.Match, Resources: steps})
			}
		}
	}

	return units
}

// TwinJudge is what Python answers for the twin reading.
type TwinJudge struct {
	program *Program
}

// Twins is the Python judge of the program.
func (p *Program) Twins() TwinJudge {
	return TwinJudge{program: p}
}

// IsType says whether a resource is a class a def builds or names rather than a call it makes.
func (TwinJudge) IsType(resource string) bool {
	return IsTypeResource(resource)
}

// ArePolymorphicSiblings says whether the two are one method answering a contract each inherits.
func (j TwinJudge) ArePolymorphicSiblings(poorer, richer engine.ReachedUnit) bool {
	one, other := Node{poorer.Match}, Node{richer.Match}

	return one.Name() == other.Name() && j.program.IsOverride(one) && j.program.IsOverride(other)
}

// ResultsAreIncomparable says whether both declare a result and they cannot be one value: a class of the program
// beside a builtin, two unrelated classes, or two different builtins.
func (j TwinJudge) ResultsAreIncomparable(poorer, richer engine.ReachedUnit) bool {
	one, other := Node{poorer.Match}.ReturnedTypeName(), Node{richer.Match}.ReturnedTypeName()
	if one == "" || other == "" || one == other {
		return false
	}
	oneClass, found := j.program.ClassCalled(one)
	otherClass, alsoFound := j.program.ClassCalled(other)
	if !found || !alsoFound {
		return true
	}

	return !derives(oneClass, other) && !derives(otherClass, one)
}

// derives says whether the class names the other directly among its bases.
func derives(class Node, name string) bool {
	return slices.ContainsFunc(class.ChildrenIn("bases"), func(base Node) bool { return lastPart(base.DottedName()) == lastPart(name) })
}

func lastPart(dotted string) string {
	return dotted[strings.LastIndex(dotted, ".")+1:]
}

// CallersOf is where each def that calls the unit is declared.
func (j TwinJudge) CallersOf(unit engine.ReachedUnit) []string {
	var callers []string
	for _, call := range j.program.CallersOf(Node{unit.Match}) {
		if caller := call.EnclosingFunction(); caller.Exists() && !slices.Contains(callers, DeclarationOf(caller)) {
			callers = append(callers, DeclarationOf(caller))
		}
	}

	return callers
}
