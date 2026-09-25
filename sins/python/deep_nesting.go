package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DeepNesting is an `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops.
type DeepNesting struct{}

func init() {
	sins.Register(catalog.Python, DeepNesting{})
}

// Definition is what the sin states about itself.
func (DeepNesting) Definition() sins.Definition {
	return sins.Definition{
		Name:        "deep-python-nesting",
		Skill:       skills.Flow{},
		Description: "An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops",
		Rule:        "Flatten with guard clauses and extraction — never bury a choice four deep inside a function.",
		Suggestion:  "Guard the outer levels away (`return`/`continue` past what does not apply), let a comprehension do the inner iteration, or extract the inner block into a function named for what it decides.",
	}
}
