package golang

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the Go part of a codebase, opening queries over Go nodes only, as every other language's does: golang.In(codebase).WhereIs(engine.Branch).Where(...).
type Codebase struct {
	*engine.Codebase
}

func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.Go)}
}

func (c *Codebase) WhereChoice() *engine.Query {
	return c.Where(engine.As(Node.IsChoice))
}

// Node is the Go view of a match: what only a reader of Go can say about a Go node.
type Node struct {
	engine.Match
}

func (Node) Decorate(match engine.Match) Node {
	return Node{match}
}

func (n Node) ChoicesAbove() int {
	choices := 0
	for _, above := range n.Ancestors() {
		if above.Is(engine.Function) {
			break
		}
		if isChoice(above) {
			choices++
		}
	}

	return choices
}

func (n Node) IsChoice() bool {
	return isChoice(n.Match)
}

func isChoice(match engine.Match) bool {
	if match.IsContinuation() {
		return false
	}

	return match.Is(engine.Branch) || match.Is(engine.Loop)
}
