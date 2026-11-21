package golang

import (
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the Go part of a codebase, opening queries over Go nodes only, as every other language's does: golang.In(codebase).WhereIs(engine.Branch).Where(...).
type Codebase struct {
	*engine.Codebase
}

// Node is the Go view of a match: what only a reader of Go can say about a Go node.
type Node struct {
	engine.Match
}
