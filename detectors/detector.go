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
	matchesEngine := func(detector Detector) bool {
		return asked == "" || slices.ContainsFunc(EnginesOf(detector), func(own catalog.Engine) bool { return catalog.Normalise(string(own)) == asked })
	}

	var named []Detector
	for _, detector := range detectors {
		if !matchesEngine(detector) {
			continue
		}
		if !slices.ContainsFunc(catalog.Names(detector), func(name string) bool { return catalog.Normalise(strings.TrimSuffix(name, "Detector")) == wanted }) {
			continue
		}

		named = append(named, detector)
	}

	if len(named) > 0 {
		return named
	}

	for _, detector := range detectors {
		if matchesEngine(detector) && slices.ContainsFunc(detector.Sin().Definition().Names(), func(name string) bool { return catalog.Normalise(name) == wanted }) {
			return []Detector{detector}
		}
	}

	return nil
}

func OnePerSin(list []Detector) []Detector {
	var kept []Detector
	for _, detector := range list {
		if !slices.ContainsFunc(kept, func(taken Detector) bool { return SameSin(taken, detector) }) {
			kept = append(kept, detector)
		}
	}

	return kept
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

// SameSin says whether two detectors find one sin: the same name under the same engine. A sin is compared by what
// it is, never by its value, since a rule's sin holds lists.
func SameSin(one, other Detector) bool {
	oneEngine, _ := EngineOf(one)
	otherEngine, _ := EngineOf(other)

	return one.Sin().Definition().Name == other.Sin().Definition().Name && oneEngine == otherEngine
}
