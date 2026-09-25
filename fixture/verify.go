package fixture

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// Result is how one detector fared against the fixture's markers.
type Result struct {
	Detector   string
	Missed     []string
	Unexpected []string
}

// Passed says whether the detector flagged exactly what its markers mark.
func (r Result) Passed() bool {
	return len(r.Missed) == 0 && len(r.Unexpected) == 0
}

// Verify runs each detector over the fixture and compares its findings with the @sin markers that
// name it or its sin: a marker nothing flagged is missed, a finding no marker covers is unexpected.
func Verify(codebase *engine.Codebase, verified ...detectors.Detector) []Result {
	markers := Markers(codebase)
	results := make([]Result, 0, len(verified))
	for _, detector := range verified {
		results = append(results, verify(codebase, detector, markers))
	}

	return results
}

func verify(codebase *engine.Codebase, detector detectors.Detector, markers []Marker) Result {
	result := Result{Detector: catalog.Name(detector)}
	var sinful []Marker
	for _, marker := range markers {
		if marker.Tag == Sinful && marker.Names(result.Detector, catalog.Name(detector.Sin())) {
			sinful = append(sinful, marker)
		}
	}
	hit := make([]bool, len(sinful))
	for _, finding := range detector.Find(codebase) {
		covering := covering(sinful, finding)
		if covering < 0 {
			result.Unexpected = append(result.Unexpected, finding.Location())

			continue
		}
		hit[covering] = true
	}
	for index, marker := range sinful {
		if !hit[index] {
			result.Missed = append(result.Missed, marker.Location)
		}
	}

	return result
}

// covering is the index of the first marker that covers the finding, -1 when none does.
func covering(markers []Marker, finding engine.Match) int {
	for index, marker := range markers {
		if marker.Covers(finding) {
			return index
		}
	}

	return -1
}
