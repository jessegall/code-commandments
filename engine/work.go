package engine

import "sync/atomic"

// Work is how much a judging really did, counted rather than timed: a count is the same on a busy machine as on an
// idle one, so it can be held to a number where a duration can only be read. Nodes is every node a query looked
// at, Checks every check it put to one, and Queries how many queries were opened.
type Work struct {
	Nodes   int64
	Checks  int64
	Queries int64
}

var nodes, checks, queries atomic.Int64

func Counted() Work {
	return Work{Nodes: nodes.Load(), Checks: checks.Load(), Queries: queries.Load()}
}

func Since(before Work) Work {
	now := Counted()

	return Work{Nodes: now.Nodes - before.Nodes, Checks: now.Checks - before.Checks, Queries: now.Queries - before.Queries}
}
