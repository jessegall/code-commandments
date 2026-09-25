package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// RepeatedGuard is the same compound condition — `order.Paid && !order.Cancelled` — written at two or more sites, a question with no name.
type RepeatedGuard struct{}

func init() {
	sins.Register(catalog.CSharp, RepeatedGuard{})
}

// Definition is what the sin states about itself.
func (RepeatedGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-repeated-guard",
		Skill:       skills.RepeatedCallHelper{},
		Description: "the same compound condition — `order.Paid && !order.Cancelled` — written at two or more sites, a question with no name",
		Rule:        "Name a compound condition asked in more than one place once, on the type it is about, and ask it by that name.",
		Suggestion:  "Add `public bool IsShippable => Paid && !Cancelled;` to the type and write `if (order.IsShippable)` at every site.",
	}
}
