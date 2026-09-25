package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// LoopInvertedGuard is the loop-inverted-guard sin.
type LoopInvertedGuard struct{}

func init() { sins.Register(catalog.Backend, LoopInvertedGuard{}) }

// Definition is what the sin states about itself.
func (LoopInvertedGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "loop-inverted-guard",
		Skill:       skills.GuardClausesAndFlow{},
		Description: "Loop body (multi-statement) wrapped in an `if` instead of `continue` guard",
		Rule:        "Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`.",
	}
}
