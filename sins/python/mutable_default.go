package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MutableDefault is a parameter defaulted to a container, made once when the def is defined and shared by every
// call that leaves it out.
type MutableDefault struct{}

func init() {
	sins.Register(catalog.Python, MutableDefault{})
}

// Definition is what the sin states about itself.
func (MutableDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-mutable-default",
		Skill:       skills.FixAtTheSource{},
		Description: "a parameter defaulted to a `[]`, `{}` or `set()` — one container, made once, shared by every call that omits it",
		Rule:        "Default a parameter to an immutable value; never to a container, which is built once when the def is defined and outlives every call.",
		Suggestion:  "Default it to `None` and make the container inside the body, so each call gets its own.",
	}
}
