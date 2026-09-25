package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// PositionalTupleReturn is a method that returns `(decimal, decimal, string)` — unnamed values the caller reads by position, where two of the same type can be swapped and nothing notices.
type PositionalTupleReturn struct{}

func init() {
	sins.Register(catalog.CSharp, PositionalTupleReturn{})
}

// Definition is what the sin states about itself.
func (PositionalTupleReturn) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-positional-tuple-return",
		Skill:       skills.ValueObjects{},
		Description: "a method that returns `(decimal, decimal, string)` — unnamed values the caller reads by position, where two of the same type can be swapped and nothing notices",
		Rule:        "Return a `record` or a tuple with named slots, not a tuple the caller has to read by position.",
		Suggestion:  "Declare `public sealed record Totals(decimal Net, decimal Vat, string Currency);` and return it — or at least name the slots: `(decimal Net, decimal Vat, string Currency)`.",
	}
}
