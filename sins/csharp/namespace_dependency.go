package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// NamespaceDependency is a reference out of a declared layer into a namespace that layer did not declare it may use.
type NamespaceDependency struct{}

func init() {
	sins.Register(catalog.CSharp, NamespaceDependency{})
}

// Definition is what the sin states about itself.
func (NamespaceDependency) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-namespace-dependency",
		Skill:       skills.DependencyDirection{},
		Description: "a reference out of a declared layer into a namespace that layer did not declare it may use",
		Rule:        "A declared layer may only use the namespaces it declared in its `mayUse` — down the stack, never back up or sideways.",
		Suggestion:  "Move what both need down into the lower layer, pass it in from above, or invert it behind an interface the lower layer owns — and if the declaration is what is wrong, say so rather than editing it quietly.",
	}
}
