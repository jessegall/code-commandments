package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// TypeSwitch is the type-switch sin.
type TypeSwitch struct{}

func init() { sins.Register(catalog.Backend, TypeSwitch{}) }

// Definition is what the sin states about itself.
func (TypeSwitch) Definition() sins.Definition {
	return sins.Definition{
		Name:        "type-switch",
		Skill:       skills.TellDontAsk{},
		Description: `two or more ` + "`" + `instanceof` + "`" + ` tests on the same subject deciding different branches — asking a value what it IS instead of telling it what to do`,
		Rule:        `Let each type answer for itself; never branch on what a value IS when a method on it could say what it DOES.`,
		Suggestion:  "A method on the shared interface, implemented per type — so a new type needs no edit here at all.",
	}
}
