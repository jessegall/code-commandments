package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RedundantElse is the redundant-else sin.
type RedundantElse struct{}

func init() { sins.Register(catalog.Backend, RedundantElse{}) }

// Definition is what the sin states about itself.
func (RedundantElse) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-else",
		Skill:       skills.GuardClausesAndFlow{},
		Description: "`else` after an `if` branch that already returns/throws (redundant)",
		Rule:        "Drop the `else` after an `if` branch that already returns/throws/continues/breaks.",
	}
}
