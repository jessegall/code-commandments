package detectors

import "github.com/jessegall/code-commandments/engine"

// Recurring is each group of candidates that share a key, two or more to a group, in the order each group
// first appears; a candidate whose key is empty belongs to none.
func Recurring(candidates []engine.Match, key func(engine.Match) string) [][]engine.Match {
	groups := map[string][]engine.Match{}
	var order []string
	for _, candidate := range candidates {
		each := key(candidate)
		if each == "" {
			continue
		}
		if _, seen := groups[each]; !seen {
			order = append(order, each)
		}
		groups[each] = append(groups[each], candidate)
	}
	var recurring [][]engine.Match
	for _, each := range order {
		if len(groups[each]) >= 2 {
			recurring = append(recurring, groups[each])
		}
	}

	return recurring
}

// NearCopies is the members of the candidates' recurring groups that have no exact twin among them: the
// near copies. A member with an exact twin is the exact-duplicate rule's finding, so the two never report
// the same line.
func NearCopies(candidates []engine.Match, near, exact func(engine.Match) string) []engine.Match {
	copies := map[string]int{}
	for _, candidate := range candidates {
		copies[exact(candidate)]++
	}
	var found []engine.Match
	for _, group := range Recurring(candidates, near) {
		for _, candidate := range group {
			if copies[exact(candidate)] == 1 {
				found = append(found, candidate)
			}
		}
	}

	return found
}
