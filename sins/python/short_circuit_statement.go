package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ShortCircuitStatement is a bare `a and b()` or `a or b()` statement — an `and`/`or` whose value nothing reads, so the operator is really acting as an `if`.
type ShortCircuitStatement struct{}

func init() {
	sins.Register(catalog.Python, ShortCircuitStatement{})
}

// Definition is what the sin states about itself.
func (ShortCircuitStatement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-short-circuit-statement",
		Skill:       skills.Flow{},
		Description: "a bare `a and b()` or `a or b()` statement — an `and`/`or` whose value nothing reads, so the operator is really acting as an `if`.",
		Rule:        "Branch with an `if`; never run work off the right side of a bare `and`/`or` statement whose value nothing reads.",
		Suggestion:  "Write the condition as an `if` and the right side as its body — `if not a: b()` for an `or`.",
	}
}
