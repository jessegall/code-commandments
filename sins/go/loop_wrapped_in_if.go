package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/go"
)

// LoopWrappedInIf is a `for` whose whole body is one `if`, burying the work a level deep where `if !ok { continue }` would keep it flat.
type LoopWrappedInIf struct{}

func init() {
	sins.Register(catalog.Go, LoopWrappedInIf{})
}

// Definition is what the sin states about itself.
func (LoopWrappedInIf) Definition() sins.Definition {
	return sins.Definition{
		Name:        "go-loop-wrapped-in-if",
		Skill:       skills.Flow{},
		Description: "A `for` whose whole body is one `if`, burying the work a level deep where `continue` past what does not apply would keep it flat",
		Rule:        "Invert a loop body wrapped in one `if` into a `continue` guard so the work sits at the loop's own level.",
		Suggestion:  "Write `if !<condition> { continue }` as the loop's first statement and dedent the body under it.",
	}
}
