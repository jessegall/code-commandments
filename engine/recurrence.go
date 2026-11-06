package engine

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

func NearCopies(candidates []Match, key, exact func(Match) (string, bool)) []Match {
	candidates = targets(candidates)
	var near []Match
	for _, at := range NearCopyPositions(len(candidates), func(at int) (string, bool) { return key(candidates[at]) }, func(at int) (string, bool) { return exact(candidates[at]) }) {
		near = append(near, candidates[at])
	}

	return near
}

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
