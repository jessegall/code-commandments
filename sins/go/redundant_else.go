package golang

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/go"
)

// RedundantElse is an `else` after an `if` branch that already left — it ends in `return`, `break`, `continue`, `goto` or `panic` — indenting the rest of the function for nothing.
type RedundantElse struct{}

func init() {
	sins.Register(catalog.Go, RedundantElse{})
}

// Definition is what the sin states about itself.
func (RedundantElse) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-go-else",
		Skill:       skills.Flow{},
		Description: "An `else` after an `if` branch that already left — it ends in `return`, `break`, `continue`, `goto` or `panic` — indenting the rest of the function for nothing",
		Rule:        "Drop the `else` after a branch that returns, breaks, continues, goes to or panics — let the rest run at the function's own level.",
		Suggestion:  "Delete the `else {` and its closing brace and dedent what was inside; the exit above it already says the rest only runs when the condition did not hold.",
	}
}
