package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// DeepNesting is an `if`, loop or `switch` opening a fourth level of choices inside one C# method — an arrow of conditions and loops.
type DeepNesting struct{}

func init() {
	sins.Register(catalog.CSharp, DeepNesting{})
}

// Definition is what the sin states about itself.
func (DeepNesting) Definition() sins.Definition {
	return sins.Definition{
		Name:        "deep-csharp-nesting",
		Skill:       skills.Flow{},
		Description: "An `if`, loop or `switch` opening a fourth level of choices inside one C# method — an arrow of conditions and loops",
		Rule:        "Flatten with guard clauses and extraction — never bury a choice four deep inside a method.",
		Suggestion:  "Guard the outer levels away (`return`/`continue` past what does not apply), let LINQ do the inner iteration, or extract the inner block into a method named for what it decides.",
	}
}
