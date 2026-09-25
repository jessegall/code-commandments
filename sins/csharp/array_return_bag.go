package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// ArrayReturnBag is a method that returns `new Dictionary<string, object> { ["sku"] = …, ["qty"] = … }` — a record with fixed fields, handed back as a dictionary.
type ArrayReturnBag struct{}

func init() {
	sins.Register(catalog.CSharp, ArrayReturnBag{})
}

// Definition is what the sin states about itself.
func (ArrayReturnBag) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-array-return-bag",
		Skill:       skills.ValueObjects{},
		Description: "a method that returns `new Dictionary<string, object> { [\"sku\"] = …, [\"qty\"] = … }` — a record with fixed fields, handed back as a dictionary",
		Rule:        "Return a `record` with those fields, not a dictionary built with fixed string keys.",
		Suggestion:  "Declare `public sealed record StockLine(string Sku, int Qty);` and return `new StockLine(sku, qty)`.",
	}
}
