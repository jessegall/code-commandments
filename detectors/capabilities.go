package detectors

import "github.com/jessegall/code-commandments/engine"

// WholeTree marks a detector whose verdict needs the whole tree: shown one file it can find the wrong thing, so a
// per-file check must not ask it.
type WholeTree interface {
	WholeTree()
}

// CrossFile marks a detector the PHP tool's CrossFileSet analysis finds reaching beyond the file it judges: by what
// it calls, through the engine's whole-program questions, rather than by a verdict it declares.
type CrossFile interface {
	CrossFile()
}

// ReadsBeyondOneFile says whether shown one file the detector could find the wrong thing, as the PHP tool's
// CrossFileSet answers it: its verdict needs the whole tree, it groups recurrences across files, or it reaches beyond
// the file by what it calls. A per-file check asks only the rest.
func ReadsBeyondOneFile(detector Detector) bool {
	_, wholeTree := detector.(WholeTree)
	_, grouped := detector.(Grouped)
	_, crossFile := detector.(CrossFile)

	return wholeTree || grouped || crossFile
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
