package engine

// Check is one question a query asks of a match.
type Check func(Match) bool

// Query is a selection of nodes narrowed one check at a time; nothing runs until a terminal asks.
type Query struct {
	selected func(yield func(Match) bool)
	checks   []Check
}

// Where keeps the matches the check answers yes to.
func (q *Query) Where(check Check) *Query {
	q.checks = append(q.checks, check)

	return q
}

// Reject drops the matches the check answers yes to.
func (q *Query) Reject(check Check) *Query {
	q.checks = append(q.checks, func(m Match) bool { return !check(m) })

	return q
}

// Get is every match that passed every check, in file and pre-order.
func (q *Query) Get() []Match {
	var matches []Match
	for match := range q.selected {
		if q.passes(match) {
			matches = append(matches, match)
		}
	}

	return matches
}

// Locations is every match's path:line.
func (q *Query) Locations() []string {
	var locations []string
	for _, match := range q.Get() {
		locations = append(locations, match.Location())
	}

	return locations
}

// Count is how many matches passed.
func (q *Query) Count() int {
	return len(q.Get())
}

// First is the first match that passed; no node when none did.
func (q *Query) First() Match {
	for match := range q.selected {
		if q.passes(match) {
			return match
		}
	}

	return Match{}
}

// passes says whether the match answers yes to every check.
func (q *Query) passes(match Match) bool {
	for _, check := range q.checks {
		if !check(match) {
			return false
		}
	}

	return true
}

// Answers keeps the nodes that answer a neutral kind: Where(engine.Answers(engine.NullSafe)).
func Answers(neutral Neutral) Check {
	return func(m Match) bool { return m.Is(neutral) }
}
