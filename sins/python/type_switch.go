package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// TypeSwitch is an `isinstance` ladder over classes the codebase owns — the value is asked what it is so the caller can decide what to do.
type TypeSwitch struct{}

func init() {
	sins.Register(catalog.Python, TypeSwitch{})
}

// Definition is what the sin states about itself.
func (TypeSwitch) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-type-switch",
		Skill:       skills.TellDontAsk{},
		Description: "an `isinstance` ladder over classes the codebase owns — the value is asked what it is so the caller can decide what to do.",
		Rule:        "Give each type the method and call it (`shape.area()`) instead of asking a value what it is in an `isinstance` ladder.",
		Suggestion:  "Declare the method on the shared base, implement it on each class, and replace the ladder with the call.",
	}
}
