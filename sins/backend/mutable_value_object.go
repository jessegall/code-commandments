package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MutableValueObject is the mutable-value-object sin.
type MutableValueObject struct{}

func init() { sins.Register(catalog.Backend, MutableValueObject{}) }

// Definition is what the sin states about itself.
func (MutableValueObject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "mutable-value-object",
		Skill:       skills.ValueObjects{},
		Description: `A value type that mutates its own field after construction, so two things holding what should be the same value can end up different — one changes without the other knowing.`,
		Rule:        `Make a value immutable: build it complete and derive a NEW one to change it; never write its fields after construction.`,
		Suggestion:  `` + "`" + `readonly` + "`" + ` on the class, and a ` + "`" + `with…()` + "`" + `/named derivation that returns a new instance (PHP 8.5's ` + "`" + `clone with` + "`" + `).`,
	}
}
