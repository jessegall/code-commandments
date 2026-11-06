package detectors

import "github.com/jessegall/code-commandments/catalog"

// Tuning is one project's setting of one detector, the Go form of a config's configure closure.
type Tuning struct {
	target string
	tune   func(Detector) (Detector, bool)
}

func Tune[D Detector](tune func(D) D) Tuning {
	var target D

	return Tuning{
		target: catalog.Name(target),
		tune: func(detector Detector) (Detector, bool) {
			typed, ok := detector.(D)
			if !ok {
				return detector, false
			}

			return tune(typed), true
		},
	}
}

func Tuned(detectors []Detector, tunings ...Tuning) ([]Detector, []string) {
	tuned := append([]Detector(nil), detectors...)
	var unmatched []string
	for _, tuning := range tunings {
		found := false
		for at, detector := range tuned {
			if set, ok := tuning.tune(detector); ok {
				tuned[at], found = set, true

				break
			}
		}
		if !found {
			unmatched = append(unmatched, tuning.target)
		}
	}

	return tuned, unmatched
}
