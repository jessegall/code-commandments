package engine

// RecurringBuckets groups the candidates by the key each one reads as, in first-seen order, and keeps the groups
// that recur at least minimum times. A candidate that reads as no key is left out.
func RecurringBuckets(candidates []Match, key func(Match) (string, bool), minimum int) [][]Match {
	var order []string
	buckets := map[string][]Match{}
	for _, candidate := range candidates {
		read, ok := key(candidate)
		if !ok {
			continue
		}
		if _, seen := buckets[read]; !seen {
			order = append(order, read)
		}
		buckets[read] = append(buckets[read], candidate)
	}
	var recurring [][]Match
	for _, read := range order {
		if len(buckets[read]) >= minimum {
			recurring = append(recurring, buckets[read])
		}
	}

	return recurring
}

// NearCopies is every candidate of a recurring group that is no exact copy of another: alike under the key, yet
// the only one of its exact reading.
func NearCopies(candidates []Match, key, exact func(Match) (string, bool)) []Match {
	copies := map[string]int{}
	for _, candidate := range candidates {
		if read, ok := exact(candidate); ok {
			copies[read]++
		}
	}
	var near []Match
	for _, bucket := range RecurringBuckets(candidates, key, 2) {
		for _, candidate := range bucket {
			if read, _ := exact(candidate); copies[read] == 1 {
				near = append(near, candidate)
			}
		}
	}

	return near
}
