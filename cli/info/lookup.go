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
	"github.com/jessegall/code-commandments/rule"
)

func Detectors(query string) []rule.Detector {
	needle := normalise(query)
	var found []rule.Detector
	shared := map[string]bool{}

	for _, detector := range config.InClassOrder(config.Detector, rule.All()) {
		if !matches(detector, query, needle) {
			continue
		}
		definition := detector.Sin().Definition()
		if strings.HasPrefix(definition.Slug(), "shared/") {
			if shared[definition.Name] {
				continue
			}
			shared[definition.Name] = true
		}
		found = append(found, detector)
	}

	for _, own := range ownRules() {
		if matches(own, query, needle) {
			found = append(found, own)
		}
	}

	return found
}

func matches(detector rule.Detector, query, needle string) bool {
	return detector.Sin().Definition().Matches(query) || len(rule.EveryNamedIn([]rule.Detector{detector}, query)) == 1 ||
		slices.ContainsFunc(catalog.Names(detector), func(name string) bool { return strings.Contains(normalise(name), needle) })
}

func ownRules() []rule.Detector {
	cwd, err := os.Getwd()
	if err != nil {
		return nil
	}

	var own []rule.Detector
	for _, each := range custom.InForce(workspace.ProjectRoot(cwd)).Rules {
		own = append(own, each)
	}

	return own
}

func Suggestions(limit int) []string {
	var names []string

	for _, detector := range rule.All() {
		names = append(names, detector.Sin().Definition().Name)
	}

	slices.Sort(names)
	names = slices.Compact(names)

	return names[:min(limit, len(names))]
}

func normalise(text string) string {
	return strings.ToLower(strings.NewReplacer("-", "", "_", "", " ", "", `\`, "").Replace(text))
}
