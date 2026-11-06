package python

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

// twins are the program's twin units and divergent pairs, read once.
type twins struct {
	once  sync.Once
	units []engine.ReachedUnit
	pairs []engine.Divergence
}

func (p *Program) TwinUnits() []engine.ReachedUnit {
	p.read()

	return p.twins.units
}

func (p *Program) Divergences() []engine.Divergence {
	p.read()

	return p.twins.pairs
}

func (p *Program) IsDivergentTwin(def Node) bool {
	return engine.PairOf(p.Divergences(), DeclarationOf(def)) != ""
}

func (p *Program) read() {
	p.twins.once.Do(func() {
		p.twins.units = p.twinUnits()
		p.twins.pairs = engine.DivergentTwins(p.Twins(), p.twins.units)
	})
}

func (p *Program) twinUnits() []engine.ReachedUnit {
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

func (p *Program) Twins() TwinJudge {
	return TwinJudge{program: p}
}

func (TwinJudge) IsType(resource string) bool {
	return IsTypeResource(resource)
}

func (j TwinJudge) ArePolymorphicSiblings(poorer, richer engine.ReachedUnit) bool {
	one, other := Node{poorer.Match}, Node{richer.Match}

	return one.Name() == other.Name() && j.program.IsOverride(one) && j.program.IsOverride(other)
}

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

func derives(class Node, name string) bool {
	return slices.ContainsFunc(class.ChildrenIn("bases"), func(base Node) bool { return lastPart(base.DottedName()) == lastPart(name) })
}

func lastPart(dotted string) string {
	return dotted[strings.LastIndex(dotted, ".")+1:]
}

func (j TwinJudge) CallersOf(key string) []string {
	var callers []string
	for _, call := range j.program.CallersOfDeclaration(key) {
		if caller := call.EnclosingFunction(); caller.Exists() && !slices.Contains(callers, DeclarationOf(caller)) {
			callers = append(callers, DeclarationOf(caller))
		}
	}

	return callers
}
