package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// CancelledFallback is `(x or "") != ""` — a value defaulted to a blank only to be compared against that same blank, so a missing value and an empty one are treated the same without saying so.
type CancelledFallback struct{}

func init() {
	sins.Register(catalog.Python, CancelledFallback{})
}

// Definition is what the sin states about itself.
func (CancelledFallback) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-cancelled-fallback",
		Skill:       skills.Absence{},
		Description: "`(x or \"\") != \"\"` — a value defaulted to a blank only to be compared against that same blank, so a missing value and an empty one are treated the same without saying so.",
		Rule:        "Ask about absence directly (`x is not None`); never default a value only to compare it against that same default.",
		Suggestion:  "Write both checks explicitly — `x is not None and x != \"\"` — or make the value non-optional at its source so only one question is left.",
	}
}
