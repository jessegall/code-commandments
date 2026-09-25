package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// NearDuplicateMethod is a near-copy — two+ C# methods, accessors or local functions with one control-flow skeleton that differ only in their local names or the literals they use (a key, a route, a message).
type NearDuplicateMethod struct{}

func init() {
	sins.Register(catalog.CSharp, NearDuplicateMethod{})
}

// Definition is what the sin states about itself.
func (NearDuplicateMethod) Definition() sins.Definition {
	return sins.Definition{
		Name:        "near-duplicate-csharp-method",
		Skill:       skills.Duplication{},
		Description: "A near-copy — two+ C# methods, accessors or local functions with one control-flow skeleton that differ only in their local names or the literals they use (a key, a route, a message)",
		Rule:        "Merge two methods that differ only in a literal into one, and pass what differs as a parameter.",
		Suggestion:  "Name the literal that differs, make it a parameter of one shared method, and call that from both places.",
	}
}
