package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// LoopWrappedInIf is a `for` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition.
type LoopWrappedInIf struct{}

func init() {
	sins.Register(catalog.Python, LoopWrappedInIf{})
}

// Definition is what the sin states about itself.
func (LoopWrappedInIf) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-loop-wrapped-in-if",
		Skill:       skills.Flow{},
		Description: "A `for` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition",
		Rule:        "Invert a loop body wrapped in one `if` into a `continue` guard so the work sits at the loop's own level.",
		Suggestion:  "Write `if not <condition>: continue` as the first line of the loop and dedent the body under it.",
	}
}
