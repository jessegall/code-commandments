package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// FilledParameter is a method that fills an object the caller handed it and returns nothing, where the caller then reads what it handed over.
type FilledParameter struct{}

func init() {
	sins.Register(catalog.PHP, FilledParameter{})
}

// Definition is what the sin states about itself.
func (FilledParameter) Definition() sins.Definition {
	return sins.Definition{
		Name:        "filled-parameter",
		Skill:       skills.TypeHonesty{},
		Description: "a `void` method that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed",
		Rule:        "A method that works something out gives it back: return the value, and let the caller decide where it goes.",
		Suggestion:  "Return what the method works out and let the caller assign it, or ask the object to change itself, so the signature says what happens.",
	}
}
