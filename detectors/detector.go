// Package detectors holds the sin detectors: thin finders over the engine, each finding one sin.
package detectors

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

// Detector finds one sin and names it; it holds no fix.
type Detector interface {
	Sin() sins.Sin
	Find(codebase *engine.Codebase) []engine.Match
}

var detectors catalog.Catalog[Detector]

// Register enrols a detector under its engine, from the detector's own file.
func Register(engine catalog.Engine, detector Detector) {
	detectors.Register(engine, detector)
}

// All is every published detector.
func All() []Detector {
	return detectors.All()
}

// Every is every detector registered under one engine, unpublished ones included.
func Every(engine catalog.Engine) []Detector {
	return detectors.Every(engine)
}

// Of is every published detector of one engine.
func Of(engine catalog.Engine) []Detector {
	return detectors.Of(engine)
}

// Engined is a detector that states its engine itself, as a project's own rule does.
type Engined interface {
	Engine() catalog.Engine
}

// EngineOf is the engine a detector judges: the one it states, else the one it was published under.
func EngineOf(detector Detector) (catalog.Engine, bool) {
	if engined, states := detector.(Engined); states {
		return engined.Engine(), true
	}

	return detectors.EngineOf(detector)
}

// Named is the published detector a name means, with or without its Detector suffix, in any case.
func Named(name string) (Detector, bool) {
	return NamedIn(All(), name)
}

// NamedIn is the detector among these a name means, with or without its Detector suffix, in any case.
func NamedIn(detectors []Detector, name string) (Detector, bool) {
	wanted := strings.ToLower(strings.TrimSuffix(name, "Detector"))
	for _, detector := range detectors {
		if strings.ToLower(strings.TrimSuffix(catalog.Name(detector), "Detector")) == wanted {
			return detector, true
		}
	}

	return nil, false
}

// Grouped is a detector whose findings recur in groups: the key says which group a finding is in, so a
// report can name the other members as its twins. False leaves the finding in no group.
type Grouped interface {
	GroupKey(match engine.Match) (string, bool)
}

// Aggregating is a detector whose verdict on a candidate weighs the candidates of the whole program: a group that
// recurs, a slot every call fills alike. It weighs them in two steps, so a program judged a part at a time weighs
// every part's candidates at once: Candidates reads the codebase's candidates, each with what the verdict needs to
// know of it, and Decide picks the sins among every candidate read, in the order it reports them.
type Aggregating interface {
	Candidates(codebase *engine.Codebase) []Candidate
	Decide(candidates []Candidate) []int
}

// Candidate is one place a rule weighs, and what its verdict needs to know of it. The match holds its codebase's
// trees, so it is let go once the codebase is; Decide reads the record alone.
type Candidate struct {
	At     engine.Match
	Record any
}

// Aggregate is what the aggregating detector finds in the codebase as a whole program.
func Aggregate(detector Aggregating, codebase *engine.Codebase) []engine.Match {
	candidates := detector.Candidates(codebase)
	var sins []engine.Match
	for _, at := range detector.Decide(candidates) {
		sins = append(sins, candidates[at].At)
	}

	return sins
}
