package python

import (
	"slices"
	"strings"

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

// GroupKey groups a finding with the twin it diverges from.
func (DivergentTwinDetector) GroupKey(match engine.Match) (string, bool) {
	program := py.In(match.Codebase()).Program
	units := program.TwinUnits()
	key := ""

	for _, unit := range units {
		if unit.Match.Location() == match.Location() {
			key = unit.Key
		}
	}

	for _, divergence := range engine.DivergentTwins(program.Twins(), units) {
		if divergence.Poorer == key || divergence.Richer == key {
			return strings.Join(slices.Sorted(slices.Values([]string{divergence.Poorer, divergence.Richer})), "|"), true
		}
	}

	return "", false
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (DivergentTwinDetector) WholeTree() {}
