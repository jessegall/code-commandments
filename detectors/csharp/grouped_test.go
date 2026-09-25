package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	csdetectors "github.com/jessegall/code-commandments/detectors/csharp"
	"github.com/jessegall/code-commandments/engine"
)

// grouped is the shape judge prints a finding's twins through: the group a finding recurs in.
type grouped interface {
	GroupKey(match engine.Match) (string, bool)
}

func TestEveryRecurringDetectorSaysWhichGroupAFindingIsIn(t *testing.T) {
	for _, detector := range []detectors.Detector{
		csdetectors.RepeatedGuardDetector{}, csdetectors.RepeatedTypeGuardDetector{}, csdetectors.RepeatedNamedCallDetector{},
		csdetectors.DuplicateMethodDetector{}, csdetectors.NearDuplicateMethodDetector{}, csdetectors.ConvertedArgumentDetector{},
		csdetectors.DataClumpDetector{},
	} {
		if _, ok := detector.(grouped); !ok {
			t.Errorf("%T says no group", detector)
		}
	}
}
