package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// PlaceholderFilledData is `new Card(title, "")` — a record's required `string` filled with a blank so the record can be built, hiding a missing value no type check can see.
type PlaceholderFilledData struct{}

func init() {
	sins.Register(catalog.CSharp, PlaceholderFilledData{})
}

// Definition is what the sin states about itself.
func (PlaceholderFilledData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-placeholder-filled-data",
		Skill:       skills.TypeHonesty{},
		Description: "`new Card(title, \"\")` — a record's required `string` filled with a blank so the record can be built, hiding a missing value no type check can see",
		Rule:        "A required slot means the caller has the value; fill it with the real value, never with `\"\"`.",
		Suggestion:  "Fetch the real value, or make the slot `string?` if it really can be missing — or split off a smaller record that only promises what you have.",
	}
}
