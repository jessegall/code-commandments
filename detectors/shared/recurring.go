package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// RecurringRule finds occurrences by a key that can be compared between separately checked files.
type RecurringRule interface {
	GroupedRule
	Occurrences(codebase *engine.Codebase) []engine.Match
}

// RecurringDetector judges occurrences together even when their trees arrive separately.
type RecurringDetector struct {
	GroupedDetector
	recurring RecurringRule
}

func BindRecurring[R RecurringRule, L Language](options ...Option) RecurringDetector {
	var rule R
	return register(RecurringDetector{GroupedDetector{bound[R, L](options), rule}, rule})
}

func (d RecurringDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

func (d RecurringDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	var candidates []detectors.Candidate
	for _, match := range d.recurring.Occurrences(codebase.Of(catalog.LanguagesOf(d.Engine())...)) {
		if key, ok := d.GroupKey(match); ok {
			candidates = append(candidates, detectors.Candidate{At: match, Record: key})
		}
	}
	return candidates
}

func (RecurringDetector) Decide(candidates []detectors.Candidate) []int {
	groups := engine.Recurring(len(candidates), func(at int) (string, bool) {
		key, ok := candidates[at].Record.(string)
		return key, ok
	}, 2)
	var found []int
	for _, group := range groups {
		found = append(found, group...)
	}
	return found
}
