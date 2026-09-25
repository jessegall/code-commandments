package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NamespaceDependency is an import out of a declared layer into a package that layer did not declare it may use.
type NamespaceDependency struct{}

func init() {
	sins.Register(catalog.Python, NamespaceDependency{})
}

// Definition is what the sin states about itself.
func (NamespaceDependency) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-namespace-dependency",
		Skill:       skills.DependencyDirection{},
		Description: "an import out of a declared layer into a package that layer did not declare it may use",
		Rule:        "A declared layer may only import the packages it declared in its `mayUse` — down the stack, never back up or sideways.",
		Suggestion:  "Move what both need down into the lower layer, pass it in from above, or invert it behind a protocol the lower layer owns — and if the declaration is what is wrong, say so rather than editing it quietly.",
	}
}
