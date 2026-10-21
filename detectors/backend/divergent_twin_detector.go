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
func (DivergentTwinDetector) GroupKey(finding engine.Match) (string, bool) {
	key := engine.PairOf(php.Divergences(finding.Codebase()), php.ScopeOf(finding))

	return key, key != ""
}

// Find is both methods of every divergent pair, the poorer first.
func (DivergentTwinDetector) Find(codebase *engine.Codebase) []engine.Match {
	units := map[string]engine.Match{}
	for _, unit := range php.TwinUnits(codebase) {
		units[unit.Key] = unit.Match
	}
	var findings []engine.Match
	for _, divergence := range php.Divergences(codebase) {
		findings = append(findings, units[divergence.Poorer], units[divergence.Richer])
	}

	return findings
}
