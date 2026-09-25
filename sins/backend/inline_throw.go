package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// InlineThrow is the inline-throw sin.
type InlineThrow struct{}

func init() { sins.Register(catalog.Backend, InlineThrow{}) }

// Definition is what the sin states about itself.
func (InlineThrow) Definition() sins.Definition {
	return sins.Definition{
		Name:        "inline-throw",
		Skill:       skills.GuardClausesAndFlow{},
		Description: "`?? throw` fed into a call or dereferenced on the same line (inline throw mid-expression)",
		Rule:        `Guard at the top with an early ` + "`" + `throw` + "`" + `; don't bury a ` + "`" + `?? throw` + "`" + ` mid-expression feeding further work.`,
	}
}
