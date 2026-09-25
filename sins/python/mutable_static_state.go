package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MutableStaticState is a `global` written from a function, or a class attribute set from a method — state no instance owns, changed by whoever ran last.
type MutableStaticState struct{}

func init() {
	sins.Register(catalog.Python, MutableStaticState{})
}

// Definition is what the sin states about itself.
func (MutableStaticState) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-mutable-static-state",
		Skill:       skills.FixAtTheSource{},
		Description: "a `global` written from a function, or a class attribute set from a method — state no instance owns, changed by whoever ran last",
		Rule:        "Hold changing state on an instance someone owns and passes; never write a `global` or a class attribute from a function.",
		Suggestion:  "Move the state onto an object, and hand that object to the code that reads and changes it.",
	}
}
