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

// EngineOf is the engine a published detector judges.
func EngineOf(detector Detector) (catalog.Engine, bool) {
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
