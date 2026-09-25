package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// InlineThrow is a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work.
type InlineThrow struct{}

func init() {
	sins.Register(catalog.CSharp, InlineThrow{})
}

// Definition is what the sin states about itself.
func (InlineThrow) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-inline-throw",
		Skill:       skills.Flow{},
		Description: "a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work",
		Rule:        "Check at the top and throw there; don't hide a `?? throw` inside the expression that does the work.",
		Suggestion:  "Pull it out into its own line first — `var given = name ?? throw new …;` or an `is null` check with a throw — then do the work with the checked value.",
	}
}
