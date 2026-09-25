package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// RepeatedNamedCall is the same `with` copy — `order with { Status = OrderStatus.Shipped }` — written at two or more sites, an operation the record never named.
type RepeatedNamedCall struct{}

func init() {
	sins.Register(catalog.CSharp, RepeatedNamedCall{})
}

// Definition is what the sin states about itself.
func (RepeatedNamedCall) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-repeated-named-call",
		Skill:       skills.RepeatedCallHelper{},
		Description: "the same `with` copy — `order with { Status = OrderStatus.Shipped }` — written at two or more sites, an operation the record never named",
		Rule:        "Name a copy written the same way at several sites as a method on the record, and call it by that name.",
		Suggestion:  "Add `public Order Shipped() => this with { Status = OrderStatus.Shipped };` to the record and write `order.Shipped()` at every site.",
	}
}
