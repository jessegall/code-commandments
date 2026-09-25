package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// DivergentTwinDetector finds two methods doing one job where one does less of it: the fix made in one place and
// not the other.
type DivergentTwinDetector struct{}

func init() { detectors.Register(catalog.Backend, DivergentTwinDetector{}) }

// Sin is the sin the detector finds.
func (DivergentTwinDetector) Sin() sins.Sin { return backendsins.DivergentTwin{} }

// GroupKey is the pair the method belongs to, named alike from either side.
func (DivergentTwinDetector) GroupKey(finding engine.Match, codebase *engine.Codebase) string {
	return engine.PairOf(divergences(codebase), php.ScopeOf(finding))
}

// Find is both methods of every divergent pair, the poorer first.
func (DivergentTwinDetector) Find(codebase *engine.Codebase) []engine.Match {
	units := map[string]engine.Match{}
	for _, unit := range php.TwinUnits(codebase) {
		units[unit.Key] = unit.Match
	}
	var findings []engine.Match
	for _, divergence := range divergences(codebase) {
		findings = append(findings, units[divergence.Poorer], units[divergence.Richer])
	}

	return findings
}

// divergences is the codebase's divergent pairs, read once.
func divergences(codebase *engine.Codebase) []engine.Divergence {
	return engine.Analysis(codebase, "backend/divergent-twins", func(codebase *engine.Codebase) []engine.Divergence {
		return engine.DivergentTwins(php.Twins(codebase), php.TwinUnits(codebase))
	})
}
