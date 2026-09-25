package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// InArrayMirrorsEnum is `new[] { "paid", "refunded" }.Contains(status)` or `status is "paid" or "refunded"` — a list of strings that repeats the members of an enum the code already has.
type InArrayMirrorsEnum struct{}

func init() {
	sins.Register(catalog.CSharp, InArrayMirrorsEnum{})
}

// Definition is what the sin states about itself.
func (InArrayMirrorsEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-in-array-mirrors-enum",
		Skill:       skills.Enums{},
		Description: "`new[] { \"paid\", \"refunded\" }.Contains(status)` or `status is \"paid\" or \"refunded\"` — a list of strings that repeats the members of an enum the code already has",
		Rule:        "Parse the string into the enum once and test the enum; don't test it against a list of strings that repeats the enum's members.",
		Suggestion:  "Read it with `Enum.TryParse<Status>(value, ignoreCase: true, out var status)` where it comes in, then ask the enum (`status.IsSettled()`).",
	}
}
