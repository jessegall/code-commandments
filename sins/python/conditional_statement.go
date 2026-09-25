package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ConditionalStatement is a bare `a() if x else b()` statement — a conditional expression whose value nothing reads, so it chooses an action, not a value.
type ConditionalStatement struct{}

func init() {
	sins.Register(catalog.Python, ConditionalStatement{})
}

// Definition is what the sin states about itself.
func (ConditionalStatement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-conditional-statement",
		Skill:       skills.Flow{},
		Description: "a bare `a() if x else b()` statement — a conditional expression whose value nothing reads, so it chooses an action, not a value.",
		Rule:        "Choose an action with `if`/`else`; a conditional expression chooses a value, so never write one whose value nothing reads.",
		Suggestion:  "An `if x:` with each side as its own body — and no `else` at all when one side was `None`.",
	}
}
