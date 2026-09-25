package frontend

import (
	"maps"
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
)

func init() {
	detectors.Register(catalog.Frontend, PropDrillingDetector{})
}

// PropDrillingDetector finds a prop a component only passes on, bare, to a child that itself only passes it
// on: a value drilled through components that never use it. One forward is composition; it is drilling once
// the child is a conduit for the prop too.
type PropDrillingDetector struct{}

func (PropDrillingDetector) Sin() sins.Sin {
	return frontend.PropDrilling{}
}

func (PropDrillingDetector) Find(codebase *engine.Codebase) []engine.Match {
	components := vue.In(codebase)
	found := map[string]map[string][]vue.Forward{}
	passThrough := func(component vue.Component) map[string][]vue.Forward {
		if _, ok := found[component.File()]; !ok {
			found[component.File()] = component.PassThroughProps(codebase)
		}

		return found[component.File()]
	}
	drillsOn := func(forward vue.Forward) bool {
		child, ok := components.Component(forward.Element.Resolves())
		_, passes := passThrough(child)[forward.As]

		return ok && passes
	}
	var findings []engine.Match
	seen := map[*contract.Node]bool{}
	for _, file := range components.Files() {
		passed := passThrough(vue.ComponentOf(file.Match(0)))
		for _, prop := range slices.Sorted(maps.Keys(passed)) {
			for _, forward := range passed[prop] {
				if seen[forward.Element.Node()] || !drillsOn(forward) {
					continue
				}
				seen[forward.Element.Node()] = true
				findings = append(findings, forward.Element.Match)
			}
		}
	}

	return findings
}
