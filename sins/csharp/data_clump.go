package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// DataClump is the same three or more string, number, date or id parameters threaded through methods of two or more types — values that always travel together but have no type of their own.
type DataClump struct{}

func init() {
	sins.Register(catalog.CSharp, DataClump{})
}

// Definition is what the sin states about itself.
func (DataClump) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-data-clump",
		Skill:       skills.ValueObjects{},
		Description: "The same three or more string, number, date or id parameters threaded through methods of two or more types — values that always travel together but have no type of their own.",
		Rule:        "Bundle values that always travel together into one type — a record — instead of threading them side by side.",
		Suggestion:  "Name the clump as a record (a `readonly record struct` when it is small), build it once where the values meet, and pass that instead of the separate parameters.",
	}
}
