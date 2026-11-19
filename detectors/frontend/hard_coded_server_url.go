package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/published"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
)

func init() {
	detectors.Register(catalog.Vue, HardCodedServerURLDetector{})
}

// HardCodedServerURLDetector finds a path written by hand where the server declares the route by name.
type HardCodedServerURLDetector struct{}

func (HardCodedServerURLDetector) Sin() sins.Sin {
	return frontend.HardCodedServerURL{}
}

func (HardCodedServerURLDetector) Find(codebase *engine.Codebase) []engine.Match {
	routes := published.Of[published.ServerRoutes](codebase)

	return typescript.In(codebase).
		Where(engine.As(func(n typescript.Node) bool { return spellsAServerRoute(n, routes) })).
		Reject(engine.As(typescript.Node.IsJoinedIntoText)).
		Where(isHandedOn).
		Reject(engine.Match.IsTest).
		Get()
}

func spellsAServerRoute(n typescript.Node, routes []published.ServerRoutes) bool {
	path := n.SpelledPath()
	for _, declared := range routes {
		if len(path) > 0 && declared.Matches(path) {
			return true
		}
	}

	return false
}

func isHandedOn(m engine.Match) bool {
	return typescript.Of(m).IsCallArgument() || vue.IsBoundValue(m)
}

func (HardCodedServerURLDetector) CrossFile() {}
