package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NamespaceCycle is two of the project's packages import each other — a cycle that makes them one package split under two names.
type NamespaceCycle struct{}

func init() {
	sins.Register(catalog.Python, NamespaceCycle{})
}

// Definition is what the sin states about itself.
func (NamespaceCycle) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-namespace-cycle",
		Skill:       skills.DependencyDirection{},
		Description: "two of the project's packages import each other — a cycle that makes them one package split under two names.",
		Rule:        "Keep imports between the project's packages pointing one way; two packages that import each other are a cycle.",
		Suggestion:  "Move what both need into the lower package, pass it in from above, or invert it behind a protocol the lower one owns.",
	}
}
