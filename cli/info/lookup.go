// Package info is `info`: explain a rule by its sin or detector name, leniently matched: what it flags,
// why it is a sin, how it is fixed, a worked example from its skill, and the commands that act on it.
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

// Detectors are every detector whose sin the query names, or whose own name holds it: the shipped ones in
// catalog order, and then the project's OWN rules, which are the rules a reader is least likely to recognise.
//
// A project's own rule is searched whether the config turns it on or not. A reader holding a finding has it on;
// a reader deciding whether to turn it on wants it explained first, and a rule that cannot be explained until it
// is enabled is a rule nobody can read about before living with it.
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

// matches says whether the query names the detector: its sin's own name, or the detector's.
func matches(detector detectors.Detector, query, needle string) bool {
	return detector.Sin().Definition().Matches(query) || strings.Contains(normalise(catalog.Name(detector)), needle)
}

// ownRules are the rules the project wrote into .commandments/custom/, read from the folder the command runs in;
// none where there is no project to read, which is how this answers from anywhere.
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
