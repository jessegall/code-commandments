// Package info is `info`: explain a rule by its sin or detector name, leniently matched: what it flags,
// why it is a sin, how it is fixed, a worked example from its skill, and the commands that act on it.
package info

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/detectors"
)

// Detectors are every detector whose sin the query names, or whose own name holds it, in catalog order.
func Detectors(query string) []detectors.Detector {
	needle := normalise(query)
	var found []detectors.Detector

	for _, detector := range config.InClassOrder(config.Detector, detectors.All()) {
		if detector.Sin().Definition().Matches(query) || strings.Contains(normalise(catalog.Name(detector)), needle) {
			found = append(found, detector)
		}
	}

	return found
}

// Suggestions are the first sin names in alphabetical order, to show when a query names none.
func Suggestions(limit int) []string {
	var names []string

	for _, detector := range detectors.All() {
		names = append(names, detector.Sin().Definition().Name)
	}

	slices.Sort(names)
	names = slices.Compact(names)

	return names[:min(limit, len(names))]
}

// normalise is a name without case, dashes, underscores, spaces or namespace separators.
func normalise(text string) string {
	return strings.ToLower(strings.NewReplacer("-", "", "_", "", " ", "", `\`, "").Replace(text))
}
