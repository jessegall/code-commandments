package detectors

import "github.com/jessegall/code-commandments/engine"

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
