package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// IfElseLadder is the if-else-ladder sin.
type IfElseLadder struct{}

func init() { sins.Register(catalog.Backend, IfElseLadder{}) }

// Definition is what the sin states about itself.
func (IfElseLadder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "if-else-ladder",
		Skill:       skills.GuardClausesAndFlow{},
		Description: "if/elseif ladder of 4+ branches (should be match/dispatch)",
		Rule:        "Replace a 4+ branch if/elseif ladder with a `match`, a method on the type, or polymorphic dispatch.",
		Suggestion:  "A `match`, a method on the type, or polymorphic dispatch.",
	}
}
