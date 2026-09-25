package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// LoopWrappedInIf is a `for`, `foreach` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition.
type LoopWrappedInIf struct{}

func init() {
	sins.Register(catalog.CSharp, LoopWrappedInIf{})
}

// Definition is what the sin states about itself.
func (LoopWrappedInIf) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-loop-wrapped-in-if",
		Skill:       skills.Flow{},
		Description: "A `for`, `foreach` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition",
		Rule:        "Invert a loop body wrapped in one `if` into a `continue` guard so the work sits at the loop's own level.",
		Suggestion:  "Write `if (!<condition>) { continue; }` as the first statement of the loop and dedent the body under it — or, when the loop only filters, let `Where` say so.",
	}
}
