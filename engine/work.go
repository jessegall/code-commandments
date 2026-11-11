package engine

import "sync/atomic"

// Work is how much a judging really did, counted rather than timed: a count is the same on a busy machine as on an
// idle one, so it can be held to a number where a duration can only be read. Nodes is every node a query looked
// at, Checks every check it put to one, Queries how many queries were opened, Walked every file a pass over the
// codebase went by whether or not it held anything, Files every file whose text a rule read, and Parsed every file
// a bridge parsed into the tree, which is what reading a project costs.
type Work struct {
	Nodes    int64
	Checks   int64
	Queries  int64
	Analyses int64
	Parts    int64
	Visited  int64
	Resolved int64
	Walked   int64
	Files    int64
	Parsed   int64

	Judged     int64
	Remembered int64
}

var nodes, checks, queries, analyses, parts, visited, resolved, walked, files, parsed, judged, remembered atomic.Int64

func Counted() Work {
	return Work{
		Nodes: nodes.Load(), Checks: checks.Load(), Queries: queries.Load(),
		Analyses: analyses.Load(), Parts: parts.Load(), Visited: visited.Load(),
		Resolved: resolved.Load(), Walked: walked.Load(), Files: files.Load(), Parsed: parsed.Load(),
		Judged: judged.Load(), Remembered: remembered.Load(),
	}
}

func Since(before Work) Work {
	now := Counted()

	return Work{
		Nodes: now.Nodes - before.Nodes, Checks: now.Checks - before.Checks, Queries: now.Queries - before.Queries,
		Analyses: now.Analyses - before.Analyses, Parts: now.Parts - before.Parts,
		Visited:  now.Visited - before.Visited,
		Resolved: now.Resolved - before.Resolved, Walked: now.Walked - before.Walked, Files: now.Files - before.Files,
		Parsed: now.Parsed - before.Parsed,
		Judged: now.Judged - before.Judged, Remembered: now.Remembered - before.Remembered,
	}
}

func CountedAnalysis() {
	analyses.Add(1)
}

func CountedContribution() {
	parts.Add(1)
}

func CountedVisit() {
	visited.Add(1)
}

func CountedResolve() {
	resolved.Add(1)
}

func CountedWalk(files int) {
	walked.Add(int64(files))
}

func CountedParsed(count int) {
	parsed.Add(int64(count))
}

func CountedJudged(count int) {
	judged.Add(int64(count))
}

func CountedRemembered(count int) {
	remembered.Add(int64(count))
}

func CountedFile() {
	files.Add(1)
}

func (w Work) Points() int64 {
	return w.Nodes + w.Checks + w.Queries + w.Visited + w.Resolved + w.Parts + w.Walked + w.Files*10 + w.Analyses*1000
}
