package detectors

import "github.com/jessegall/code-commandments/engine"

// WholeTree marks a detector whose verdict needs the whole tree: shown one file it can find the wrong thing, so a
// per-file check must not ask it.
type WholeTree interface {
	WholeTree()
}

// Repentable marks a detector whose sin a scribe rewrites away at the source.
type Repentable interface {
	Repentable()
}

// RunsLast marks a repentable detector whose fix reshapes code the other fixes have settled, so it runs after them.
type RunsLast interface {
	RunsLast()
}

// RequiresBestDesign marks a design-smell detector: a report that it is wrong must name the cleanest design the
// reporter can conceive.
type RequiresBestDesign interface {
	RequiresBestDesign()
}

// RecurrenceDetector marks a detector whose sin is one shape recurring: one occurrence proves nothing, so it names
// the group each finding belongs to, and the fixture must show a group reaching across files.
type RecurrenceDetector interface {
	GroupKey(finding engine.Match, codebase *engine.Codebase) string
}

// ChainDetector marks a detector whose verdict follows a value through the whole program: its finding's evidence is
// the chain of kind@file steps the value took, and the fixture must show one crossing several files.
type ChainDetector interface {
	ChainPath(finding engine.Match, codebase *engine.Codebase) []string
}
