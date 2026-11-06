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

// Several is a detector that judges more than one engine, as a project's own rule may: its file names them, and
// a reader asking about ONE engine has to ask about all of them.
type Several interface {
	Engines() []catalog.Engine
}

// EnginesOf are every engine a detector judges. Nearly every rule judges one, and the first is the one EngineOf
// answers; a rule whose file names several judges them all, and reading only the first silently switched such a
// rule off wherever one engine was asked about. A consumer's rule naming go and python was never asked about a
// python file in a live check, because go was written first.
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

// IdentityOf tells one rule from another, which a NAME alone does not: a detector's catalog name is its bare
// type name, and the engines share those. Of 247 rules a project had enabled, only 127 names were distinct, so a
// set keyed by the name kept one rule of each name and dropped the rest — the python nesting rule among them,
// because the PHP rule of that name comes first in curriculum order and never matches a python file, so that sin
// was never found at all while its neighbour in the same file was.
//
// Engine and name, which is the identity the configuration already spells out as config.Rule{Kind, Engine, Name}
// and the one a project names a rule by. Keyed by the type instead, every rule a project writes of its own would
// collapse into one, since they share a type; keyed by the detector value, a rule holding a slice cannot be a
// map key at all.
func IdentityOf(detector Detector) string {
	engine, _ := EngineOf(detector)

	return string(engine) + "\x00" + catalog.Name(detector)
}

// Judges says whether the detector judges that engine.
func Judges(detector Detector, engine catalog.Engine) bool {
	return slices.Contains(EnginesOf(detector), engine)
}

// Named is the published detector a name means, with or without its Detector suffix, in any case.
func Named(name string) (Detector, bool) {
	return NamedIn(All(), name)
}

// NamedIn is the detector among these a name means: its own name, with or without its Detector suffix, or the
// name of the sin it finds, however either is spelled.
//
// A reader names a rule by whichever name they were shown, and the two surfaces show different ones: a judge
// finding and a checklist line print the rule's name, while a chat mark and `info` print the SIN's. So a report
// filed as `--detector=helper-in-a-kind-folder`, read off a mark, must mean the rule whose sin that is. It did
// not, and nothing downstream could tell: the project's-own-rule refusal and the best-design litmus both read a
// name that meant no rule as a rule they knew nothing about, so a report against a consumer's OWN rule was filed
// upstream, where nothing can fix it (issue 824).
//
// The spelling is folded for the same reason: a name is read off a line and typed back, and the case and the
// hyphens of `ArrayBag`, `array-bag` and `array_bag` are not a difference anyone means.
//
// A name several ENGINES answer to means no one rule, and is answered as none. A detector's own name is its bare
// type name and the engines share those: of 247 rules a project had enabled, only 127 names were distinct. The
// first of them was handed back, and All() puts PHP first, so `--detector=DeepNestingDetector` could only ever
// mean the PHP rule however plainly a reader meant the python one — and a false-positive report then named a
// rule the reader had never seen fire. EveryNamedIn says which they are, so a caller can ask for the one.
//
// Named with its engine, `python/DeepNestingDetector`, it is the one rule it says and nothing is ambiguous.
func NamedIn(detectors []Detector, name string) (Detector, bool) {
	named := EveryNamedIn(detectors, name)
	if len(named) != 1 {
		return nil, false
	}

	return named[0], true
}

// EveryNamedIn is every detector among these that the name means: the one an engine-qualified name says, else
// every rule of that bare name, in curriculum order. A sin's name is answered after a rule's, and is engine-
// distinct, so it means at most one.
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
