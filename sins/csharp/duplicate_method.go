package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// DuplicateMethod is copy-pasted code — two+ C# methods, accessors or local functions with an identical body, formatting, comments and attributes aside.
type DuplicateMethod struct{}

func init() {
	sins.Register(catalog.CSharp, DuplicateMethod{})
}

// Definition is what the sin states about itself.
func (DuplicateMethod) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicate-csharp-method",
		Skill:       skills.Duplication{},
		Description: "Copy-pasted code — two+ C# methods, accessors or local functions with an identical body, formatting, comments and attributes aside",
		Rule:        "Hoist a method body written twice into one shared method, and call it from both places.",
		Suggestion:  "Move the body to one method on the type that owns the data (or an extension or static helper both callers reference), and replace every copy with a call to it.",
	}
}
