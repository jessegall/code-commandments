package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// CancelledCoalesce is a `??` fallback compared against the same value it falls back to — `(name ?? "") != ""` — so "missing" and "empty" end up in one branch without saying so.
type CancelledCoalesce struct{}

func init() {
	sins.Register(catalog.CSharp, CancelledCoalesce{})
}

// Definition is what the sin states about itself.
func (CancelledCoalesce) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-cancelled-coalesce",
		Skill:       skills.Absence{},
		Description: "a `??` fallback compared against the same value it falls back to — `(name ?? \"\") != \"\"` — so \"missing\" and \"empty\" end up in one branch without saying so",
		Rule:        "Check for null directly (`name is not null`); don't fall back to a value only to compare against that same value.",
		Suggestion:  "Write both checks out — `name is not null && name != \"\"` — or make the value non-nullable where it comes from, so only one check is left.",
	}
}
