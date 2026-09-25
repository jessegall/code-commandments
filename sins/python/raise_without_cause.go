package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RaiseWithoutCause is `raise Other(...)` inside an `except` block with no `from` — the failure being handled left as an implicit context, never named as the cause.
type RaiseWithoutCause struct{}

func init() {
	sins.Register(catalog.Python, RaiseWithoutCause{})
}

// Definition is what the sin states about itself.
func (RaiseWithoutCause) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-raise-without-cause",
		Skill:       skills.Exceptions{},
		Description: "`raise Other(...)` inside an `except` block with no `from` — the failure being handled left as an implicit context, never named as the cause",
		Rule:        "Raise a new exception from inside `except` with `from error` so the cause is kept; say `from None` when cutting it is the point.",
		Suggestion:  "Bind the caught exception (`except KeyError as error:`) and raise `from error`.",
	}
}
