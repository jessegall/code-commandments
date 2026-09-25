package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// RedundantElse is an `else` after an `if` branch that already left — it ends in `return`, `throw`, `continue` or `break` — indenting the rest of the method for nothing.
type RedundantElse struct{}

func init() {
	sins.Register(catalog.CSharp, RedundantElse{})
}

// Definition is what the sin states about itself.
func (RedundantElse) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-csharp-else",
		Skill:       skills.Flow{},
		Description: "An `else` after an `if` branch that already left — it ends in `return`, `throw`, `continue` or `break` — indenting the rest of the method for nothing",
		Rule:        "Drop the `else` after a branch that returns, throws, continues or breaks — let the rest run at the method's own level.",
		Suggestion:  "Delete the `else` and its braces and dedent its block; the exit above it already says the rest only runs when the condition was false.",
	}
}
