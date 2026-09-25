package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// DivergentTwinDetector finds two defs doing one job where one does less of it: a step the other takes and it
// forgot.
type DivergentTwinDetector struct{}

func init() {
	detectors.Register(catalog.Python, DivergentTwinDetector{})
}

// Sin is the sin the detector finds.
func (DivergentTwinDetector) Sin() sins.Sin {
	return pysins.DivergentTwin{}
}

// Find is every place the sin is committed: both defs of each divergent pair.
func (DivergentTwinDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	units := program.TwinUnits()
	byKey := map[string]engine.Match{}
	for _, unit := range units {
		byKey[unit.Key] = unit.Match
	}
	var findings []engine.Match
	seen := map[string]bool{}
	for _, divergence := range engine.DivergentTwins(program.Twins(), units) {
		for _, key := range []string{divergence.Poorer, divergence.Richer} {
			if !seen[key] {
				seen[key] = true
				findings = append(findings, byKey[key])
			}
		}
	}

	return findings
}
