package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// StringMirrorsEnum is a `switch` or an `if` ladder dispatching on strings that are the names of an enum the codebase already declares — the enum, written out again as text.
type StringMirrorsEnum struct{}

func init() {
	sins.Register(catalog.CSharp, StringMirrorsEnum{})
}

// Definition is what the sin states about itself.
func (StringMirrorsEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-string-mirrors-enum",
		Skill:       skills.Enums{},
		Description: "A `switch` or an `if` ladder dispatching on strings that are the names of an enum the codebase already declares — the enum, written out again as text",
		Rule:        "Dispatch on the enum, never on strings that spell its members — parse the string into the enum where it enters.",
		Suggestion:  "Parse the string into the enum at the edge (`Enum.Parse<T>` or the JSON converter), switch on the enum, and put the per-case answer beside it.",
	}
}
