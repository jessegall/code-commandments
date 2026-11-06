// Package info is `info`: explain a rule by its sin or detector name, leniently matched: what it flags, why it is a sin, how it is fixed, a worked example from its skill, and the commands that act on it.
package info

import (
	"os"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
)

func Detectors(query string) []detectors.Detector {
	needle := normalise(query)
	var found []detectors.Detector

	for _, detector := range config.InClassOrder(config.Detector, detectors.All()) {
		if matches(detector, query, needle) {
			found = append(found, detector)
		}
	}

	for _, own := range ownRules() {
		if matches(own, query, needle) {
			found = append(found, own)
		}
	}

	return found
}

func matches(detector detectors.Detector, query, needle string) bool {
	return detector.Sin().Definition().Matches(query) || strings.Contains(normalise(catalog.Name(detector)), needle)
}

func ownRules() []detectors.Detector {
	cwd, err := os.Getwd()
	if err != nil {
		return nil
	}

	var own []detectors.Detector
	for _, rule := range custom.InForce(workspace.ProjectRoot(cwd)).Rules {
		own = append(own, rule)
	}

	return own
}

func Suggestions(limit int) []string {
	var names []string

	for _, detector := range detectors.All() {
		names = append(names, detector.Sin().Definition().Name)
	}

	slices.Sort(names)
	names = slices.Compact(names)

	return names[:min(limit, len(names))]
}

func normalise(text string) string {
	return strings.ToLower(strings.NewReplacer("-", "", "_", "", " ", "", `\`, "").Replace(text))
}
