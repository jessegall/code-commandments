package detectors_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

type counting struct{ threshold int }

func (counting) Sin() sins.Sin                        { return nil }
func (counting) Find(*engine.Codebase) []engine.Match { return nil }

type other struct{}

func (other) Sin() sins.Sin                        { return nil }
func (other) Find(*engine.Codebase) []engine.Match { return nil }

type absent struct{}

func (absent) Sin() sins.Sin                        { return nil }
func (absent) Find(*engine.Codebase) []engine.Match { return nil }

func TestATuningSetsTheDetectorOfItsTypeAndNoOther(t *testing.T) {
	registered := []detectors.Detector{other{}, counting{threshold: 2}}
	tuned, unmatched := detectors.Tuned(registered, detectors.Tune(func(d counting) counting { d.threshold = 5; return d }))
	if tuned[1] != (counting{threshold: 5}) || tuned[0] != (other{}) {
		t.Errorf("tuned %#v", tuned)
	}
	if registered[1] != (counting{threshold: 2}) {
		t.Error("tuning changed the registered detector")
	}
	if len(unmatched) != 0 {
		t.Errorf("unmatched %v", unmatched)
	}
}

func TestATuningOfADetectorNotInForceIsReported(t *testing.T) {
	_, unmatched := detectors.Tuned([]detectors.Detector{other{}}, detectors.Tune(func(d absent) absent { return d }))
	if !slices.Equal(unmatched, []string{"absent"}) {
		t.Errorf("unmatched %v", unmatched)
	}
}
