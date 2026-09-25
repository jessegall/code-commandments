package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// NamespaceCycle is two of the project's namespaces that each use the other — a cycle that makes them one namespace split under two names.
type NamespaceCycle struct{}

func init() {
	sins.Register(catalog.CSharp, NamespaceCycle{})
}

// Definition is what the sin states about itself.
func (NamespaceCycle) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-namespace-cycle",
		Skill:       skills.DependencyDirection{},
		Description: "two of the project's namespaces that each use the other — a cycle that makes them one namespace split under two names",
		Rule:        "Keep references between the project's namespaces pointing one way; two namespaces that use each other are a cycle.",
		Suggestion:  "Cut the thinner direction: move what both need into the lower namespace, pass it in from above, or invert it behind an interface the lower one owns.",
	}
}
