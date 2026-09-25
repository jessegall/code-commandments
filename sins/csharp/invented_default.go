package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// InventedDefault is `F(x ?? "")` — an empty string, `0` or `false` invented to fill an argument, or answered by a lookup helper on a miss, a stand-in the callee cannot tell from real data.
type InventedDefault struct{}

func init() {
	sins.Register(catalog.CSharp, InventedDefault{})
}

// Definition is what the sin states about itself.
func (InventedDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-invented-default",
		Skill:       skills.Absence{},
		Description: "`F(x ?? \"\")` — an empty string, `0` or `false` invented to fill an argument, or answered by a lookup helper on a miss, a stand-in the callee cannot tell from real data",
		Rule:        "Never fill a value with an invented `\"\"`, `0` or `false` on absence — handle the missing case, or make the value certain at the point it is created.",
		Suggestion:  "Decide at the source: throw when the value must be there, or pass the absence on to a parameter typed to admit it (`T?`, `TryGetValue`). A real default (`?? \"EUR\"`) is a choice, not an invention.",
	}
}
