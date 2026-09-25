package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RedundantElse is an `else:` after an `if` branch that already left — it ends in `return`, `raise`, `continue` or `break` — indenting the rest of the function for nothing.
type RedundantElse struct{}

func init() {
	sins.Register(catalog.Python, RedundantElse{})
}

// Definition is what the sin states about itself.
func (RedundantElse) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-python-else",
		Skill:       skills.Flow{},
		Description: "An `else:` after an `if` branch that already left — it ends in `return`, `raise`, `continue` or `break` — indenting the rest of the function for nothing",
		Rule:        "Drop the `else:` after a branch that returns, raises, continues or breaks — let the rest run at the function's own level.",
		Suggestion:  "Delete the `else:` line and dedent its block; the exit above it already says the rest only runs when the condition was false.",
	}
}
