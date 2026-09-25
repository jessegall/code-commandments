package fixture

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// Prove fails the test unless every detector flags exactly its marked sins in the fixture and
// fires on at least MinScenarios mutually diverse findings.
func Prove(t testing.TB, codebase *engine.Codebase, proven ...detectors.Detector) {
	t.Helper()
	if len(proven) == 0 {
		t.Error("no detectors were verified against the fixture")
	}
	for _, result := range Verify(codebase, proven...) {
		if len(result.Missed) > 0 {
			t.Errorf("%s missed marked sins: %v", result.Detector, result.Missed)
		}
		if len(result.Unexpected) > 0 {
			t.Errorf("%s flagged unmarked code (a false positive, or an unmarked sin): %v", result.Detector, result.Unexpected)
		}
	}
	for _, detector := range proven {
		scenarios, err := Scenarios(codebase, detector)
		if err != nil {
			t.Error(err)

			continue
		}
		if largest := LargestDiverseGroup(scenarios); largest < MinScenarios {
			t.Errorf("%s: needs ≥%d mutually-DIVERSE scenarios (different files, <%.0f%% overlap) but the largest diverse group of its %d finding(s) is %d. Add genuinely different cases, not copies.",
				catalog.Name(detector), MinScenarios, MaxSimilarity, len(scenarios), largest)
		}
	}
}
