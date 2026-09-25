package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DeepNesting is the deep-nesting sin.
type DeepNesting struct{}

func init() { sins.Register(catalog.Backend, DeepNesting{}) }

// Definition is what the sin states about itself.
func (DeepNesting) Definition() sins.Definition {
	return sins.Definition{
		Name:        "deep-nesting",
		Skill:       skills.GuardClausesAndFlow{},
		Description: "`if` nested 3-deep (a pyramid — hoist guards / extract)",
		Rule:        "Flatten with guard clauses — never nest `if`s three deep into a pyramid.",
	}
}
