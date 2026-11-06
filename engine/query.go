package engine

// Check is one question a query asks of a match.
type Check func(Match) bool

// Query is a selection of nodes narrowed one check at a time; nothing runs until a terminal asks.
type Query struct {
	selected func(yield func(Match) bool)
	checks   []Check
}

func (q *Query) Where(check Check) *Query {
	q.checks = append(q.checks, check)

	return q
}

func (q *Query) Reject(check Check) *Query {
	q.checks = append(q.checks, func(m Match) bool { return !check(m) })

	return q
}

func (q *Query) Get() []Match {
	var matches []Match
	for match := range q.selected {
		if q.passes(match) {
			matches = append(matches, match)
		}
	}

	return matches
}

func (q *Query) Locations() []string {
	var locations []string
	for _, match := range q.Get() {
		locations = append(locations, match.Location())
	}

	return locations
}

func (q *Query) Count() int {
	return len(q.Get())
}

func (q *Query) First() Match {
	for match := range q.selected {
		if q.passes(match) {
			return match
		}
	}

	return Match{}
}

func (q *Query) passes(match Match) bool {
	nodes.Add(1)

	for _, check := range q.checks {
		checks.Add(1)

		if !check(match) {
			return false
		}
	}

	return true
}

func Answers(neutral Neutral) Check {
	return func(m Match) bool { return m.Is(neutral) }
}
