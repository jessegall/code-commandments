package golang

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Codebase is the Go part of a codebase, opening queries over Go nodes only, as every other language's does:
// golang.In(codebase).WhereIs(engine.Branch).Where(...).
type Codebase struct {
	*engine.Codebase
}

// In is the Go part of the codebase.
func In(codebase *engine.Codebase) *Codebase {
	return &Codebase{Codebase: codebase.Of(contract.Go)}
}

// WhereChoice opens a query over every choice a reader of a function holds open: an `if`, a `for`, a `switch`
// and a `select`, with an else-if left out, since it continues the `if` before it rather than nesting in it.
func (c *Codebase) WhereChoice() *engine.Query {
	return c.Where(engine.As(Node.IsChoice))
}

// Node is the Go view of a match: what only a reader of Go can say about a Go node.
type Node struct {
	engine.Match
}

// Decorate is the Go view of a match.
func (Node) Decorate(match engine.Match) Node {
	return Node{match}
}

// ChoicesAbove is how many choices the node already sits inside, within its own function: every `if`, `for`,
// `switch` and `select` above it, an else-if counted with the `if` it continues rather than as one of its own.
// A function nested in another starts the count again, since its body is read on its own.
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

// IsChoice says whether the node is one of the choices a reader holds open: a branch or a loop, and not an
// else-if, which continues the `if` before it rather than nesting inside it.
func (n Node) IsChoice() bool {
	return isChoice(n.Match)
}

// isChoice is the one reading of what counts as a choice, asked of a node whichever way it was reached.
func isChoice(match engine.Match) bool {
	if match.IsContinuation() {
		return false
	}

	return match.Is(engine.Branch) || match.Is(engine.Loop)
}
