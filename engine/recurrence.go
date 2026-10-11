package engine

// Recurring is the positions whose keys recur at least minimum times, grouped by key, each group in the order its
// key is first read. A position whose key is not read belongs to none.
func Recurring(count int, key func(at int) (string, bool), minimum int) [][]int {
	var order []string
	groups := map[string][]int{}
	for at := range count {
		read, ok := key(at)
		if !ok {
			continue
		}
		if _, seen := groups[read]; !seen {
			order = append(order, read)
		}
		groups[read] = append(groups[read], at)
	}
	var recurring [][]int
	for _, read := range order {
		if len(groups[read]) >= minimum {
			recurring = append(recurring, groups[read])
		}
	}

	return recurring
}

// NearCopyPositions is every position of a recurring group that is no exact copy of another: alike under the key,
// yet the only one of its exact reading.
func NearCopyPositions(count int, key, exact func(at int) (string, bool)) []int {
	copies := map[string]int{}
	for at := range count {
		if read, ok := exact(at); ok {
			copies[read]++
		}
	}
	var near []int
	for _, group := range Recurring(count, key, 2) {
		for _, at := range group {
			if read, _ := exact(at); copies[read] == 1 {
				near = append(near, at)
			}
		}
	}

	return near
}

// RecurringBuckets groups the candidates by the key each one reads as, in first-seen order, and keeps the groups
// that recur at least minimum times. A candidate that reads as no key is left out, and so is one in a frozen file.
func RecurringBuckets(candidates []Match, key func(Match) (string, bool), minimum int) [][]Match {
	candidates = targets(candidates)
	var buckets [][]Match
	for _, group := range Recurring(len(candidates), func(at int) (string, bool) { return key(candidates[at]) }, minimum) {
		bucket := make([]Match, len(group))
		for i, at := range group {
			bucket[i] = candidates[at]
		}
		buckets = append(buckets, bucket)
	}

	return buckets
}

// NearCopies is every candidate of a recurring group that is no exact copy of another: alike under the key, yet
// the only one of its exact reading. A candidate in a frozen file is left out.
func NearCopies(candidates []Match, key, exact func(Match) (string, bool)) []Match {
	candidates = targets(candidates)
	var near []Match
	for _, at := range NearCopyPositions(len(candidates), func(at int) (string, bool) { return key(candidates[at]) }, func(at int) (string, bool) { return exact(candidates[at]) }) {
		near = append(near, candidates[at])
	}

	return near
}

// targets are the candidates a rule may answer about: a recurrence says one thing is written twice, so it needs
// sites that can change. A frozen file is read like any other, and its declarations go on answering every rule
// that reads across files, but it is never a target, so it cannot stand as the second site of a recurrence and
// leave the one site that can change carrying the finding.
func targets(candidates []Match) []Match {
	kept := make([]Match, 0, len(candidates))
	for _, candidate := range candidates {
		if candidate.file != nil && candidate.file.IsFrozen() {
			continue
		}
		kept = append(kept, candidate)
	}

	return kept
}
