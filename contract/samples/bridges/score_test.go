package bridges

import (
	"maps"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/contract/samples"
)

func TestEveryBridgeOwesTheWholeContract(t *testing.T) {
	if !scored.Load() {
		t.Skip("the score reads what TestEveryBridgeWritesTheExpectedTree observed: run both")
	}
	for _, language := range slices.Sorted(maps.Keys(bridgeOf)) {
		t.Run(string(language), func(t *testing.T) {
			debt, faults := samples.Owed(filepath.Join("..", "..", "coverage"), language)
			for _, fault := range faults {
				t.Error(fault)
			}
			observed := witnessed.byLanguage[language]
			var owed, proven int
			var unproven, wrong []string
			for _, item := range debt.Owed {
				owed++
				written, seen := observed[item]
				switch {
				case !seen:
					unproven = append(unproven, item)
				case !written:
					wrong = append(wrong, item)
				default:
					proven++
				}
			}
			t.Logf("%s %d/%d (%d%%), %d not owed", language, proven, owed, proven*100/max(owed, 1), len(debt.Exemptions))
			for _, item := range wrong {
				t.Errorf("%s: written otherwise than its samples expect", item)
			}
			for _, item := range unproven {
				t.Errorf("%s: no sample proves it", item)
			}
		})
	}
}
