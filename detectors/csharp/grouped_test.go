package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	csdetectors "github.com/jessegall/code-commandments/detectors/csharp"
)

func TestEveryRecurringDetectorSaysWhichGroupAFindingIsIn(t *testing.T) {
	for _, detector := range []detectors.Detector{
		csdetectors.RepeatedGuardDetector{}, csdetectors.RepeatedTypeGuardDetector{}, csdetectors.RepeatedNamedCallDetector{},
		csdetectors.DuplicateMethodDetector{}, csdetectors.NearDuplicateMethodDetector{}, csdetectors.ConvertedArgumentDetector{},
		csdetectors.DataClumpDetector{},
	} {
		if _, ok := detector.(detectors.Grouped); !ok {
			t.Errorf("%T says no group", detector)
		}
	}
}
