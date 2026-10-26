// Package go holds the Go engine's sins: each one its own type, naming the skill that teaches its fix.
package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/go"
)

// DeepNesting is an `if`, `for`, `switch` or `select` opening a fourth level of choices inside one Go function.
type DeepNesting struct{}

func init() {
	sins.Register(catalog.Go, DeepNesting{})
}

// Definition is what the sin states about itself.
func (DeepNesting) Definition() sins.Definition {
	return sins.Definition{
		Name:        "deep-go-nesting",
		Skill:       skills.Flow{},
		Description: "An `if`, `for`, `switch` or `select` opening a fourth level of choices inside one Go function — a decision buried inside a decision",
		Rule:        "Flatten with guards and extraction — never bury a choice four deep inside a function.",
		Suggestion:  "Guard the outer levels away (`return`, `break` or `continue` past what does not apply), or give the inner block a function of its own named for what it decides.",
	}
}
