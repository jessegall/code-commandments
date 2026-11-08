// Package detectors holds the sin detectors: thin finders over the engine, each finding one sin.
package detectors

import (
	"slices"
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

func Register(engine catalog.Engine, detector Detector) {
	detectors.Register(engine, detector)
}

func All() []Detector {
	return detectors.All()
}

func Every(engine catalog.Engine) []Detector {
	return detectors.Every(engine)
}

func Of(engine catalog.Engine) []Detector {
	return detectors.Of(engine)
}

// Tagged is a detector that carries words grouping it with others, as a project's own rule can, so judge --tag can run that group alone.
type Tagged interface {
	Tags() []string
}

// Engined is a detector that states its engine itself, as a project's own rule does.
type Engined interface {
	Engine() catalog.Engine
}

func EngineOf(detector Detector) (catalog.Engine, bool) {
	if engined, states := detector.(Engined); states {
		return engined.Engine(), true
	}

	return detectors.EngineOf(detector)
}

// Several is a detector that judges more than one engine, as a project's own rule may: its file names them, and a reader asking about ONE engine has to ask about all of them.
type Several interface {
	Engines() []catalog.Engine
}

func EnginesOf(detector Detector) []catalog.Engine {
	if several, states := detector.(Several); states {
		if engines := several.Engines(); len(engines) > 0 {
			return engines
		}
	}

	engine, known := EngineOf(detector)
	if !known {
		return nil
	}

	return []catalog.Engine{engine}
}

func IdentityOf(detector Detector) string {
	engine, _ := EngineOf(detector)

	return string(engine) + "\x00" + catalog.Name(detector)
}

func Judges(detector Detector, engine catalog.Engine) bool {
	return slices.Contains(EnginesOf(detector), engine)
}

func JudgesAny(detector Detector, engines []catalog.Engine) bool {
	return slices.ContainsFunc(engines, func(engine catalog.Engine) bool { return Judges(detector, engine) })
}

func Named(name string) (Detector, bool) {
	return NamedIn(All(), name)
}

func NamedIn(detectors []Detector, name string) (Detector, bool) {
	named := EveryNamedIn(detectors, name)
	if len(named) != 1 {
		return nil, false
	}

	return named[0], true
}

func EveryNamedIn(detectors []Detector, name string) []Detector {
	engine, bare, qualified := strings.Cut(name, "/")
	if !qualified {
		engine, bare = "", name
	}

	wanted := catalog.Normalise(strings.TrimSuffix(bare, "Detector"))
	asked := catalog.Normalise(engine)

	var named []Detector
	for _, detector := range detectors {
		if catalog.Normalise(strings.TrimSuffix(catalog.Name(detector), "Detector")) != wanted {
			continue
		}

		if asked != "" {
			if own, _ := EngineOf(detector); catalog.Normalise(string(own)) != asked {
				continue
			}
		}

		named = append(named, detector)
	}

	if len(named) > 0 {
		return named
	}

	for _, detector := range detectors {
		if catalog.Normalise(detector.Sin().Definition().Name) == wanted {
			return []Detector{detector}
		}
	}

	return nil
}

// Grouped is a detector whose findings recur in groups: the key says which group a finding is in, so a report can name the other members as its twins.
type Grouped interface {
	GroupKey(match engine.Match) (string, bool)
}

// Aggregating is a detector whose verdict on a candidate weighs the candidates of the whole program: a group that recurs, a slot every call fills alike.
type Aggregating interface {
	Candidates(codebase *engine.Codebase) []Candidate
	Decide(candidates []Candidate) []int
}

// Candidate is one place a rule weighs, and what its verdict needs to know of it.
type Candidate struct {
	At     engine.Match
	Record any
}

func Aggregate(detector Aggregating, codebase *engine.Codebase) []engine.Match {
	candidates := detector.Candidates(codebase)
	var sins []engine.Match
	for _, at := range detector.Decide(candidates) {
		sins = append(sins, candidates[at].At)
	}

	return sins
}
