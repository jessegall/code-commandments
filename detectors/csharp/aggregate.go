package csharp

import (
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// keyed is the record of a candidate a rule groups: the key it reads as, when it reads as one, its exact reading, and the owner it is declared in.
type keyed struct {
	key   string
	read  bool
	exact string
	owner string
}

func keyedBy(matches []engine.Match, key func(engine.Match) (string, bool)) []detectors.Candidate {
	candidates := make([]detectors.Candidate, len(matches))
	for at, match := range matches {
		read, ok := key(match)
		candidates[at] = detectors.Candidate{At: match, Record: keyed{key: read, read: ok}}
	}

	return candidates
}

func keyOf(candidates []detectors.Candidate) func(at int) (string, bool) {
	return func(at int) (string, bool) {
		record, isKeyed := candidates[at].Record.(keyed)

		return record.key, isKeyed && record.read
	}
}

func recurringAt(candidates []detectors.Candidate, minimum int) []int {
	var found []int
	for _, group := range engine.Recurring(len(candidates), keyOf(candidates), minimum) {
		found = append(found, group...)
	}

	return found
}

// supply is how many calls a codebase holds fill a parameter, `Shop.Pricing.Quote(…)#1`: a record no finding is made of, counted across every codebase a verdict weighs.
type supply struct {
	slot  string
	count int
}

// unresolved is a name some call the compiler could not resolve spells.
type unresolved struct {
	name string
}

func supplied(counts map[string]int, order []string) []detectors.Candidate {
	candidates := make([]detectors.Candidate, len(order))
	for at, slot := range order {
		candidates[at] = detectors.Candidate{Record: supply{slot: slot, count: counts[slot]}}
	}

	return candidates
}

func suppliedIn(candidates []detectors.Candidate) (map[string]int, map[string]bool) {
	counts, names := map[string]int{}, map[string]bool{}
	for _, candidate := range candidates {
		switch record := candidate.Record.(type) {
		case supply:
			counts[record.slot] += record.count
		case unresolved:
			names[record.name] = true
		}
	}

	return counts, names
}
