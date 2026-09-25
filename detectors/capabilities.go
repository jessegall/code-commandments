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

// ChainDetector marks a detector whose verdict follows a value through the whole program: its finding's evidence is
// the chain of kind@file steps the value took, and the fixture must show one crossing several files.
type ChainDetector interface {
	ChainPath(finding engine.Match, codebase *engine.Codebase) []string
}

// ConsumesContracts marks a detector whose verdict reads what another engine publishes about the codebase.
type ConsumesContracts interface {
	ConsumesContracts()
}
