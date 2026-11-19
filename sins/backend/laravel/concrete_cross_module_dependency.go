package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ConcreteCrossModuleDependency is the concrete-cross-module-dependency sin.
type ConcreteCrossModuleDependency struct{}

func init() { sins.Register(catalog.PHP, ConcreteCrossModuleDependency{}) }


func (ConcreteCrossModuleDependency) Definition() sins.Definition {
	return sins.Definition{
		Name:        "concrete-cross-module-dependency",
		Skill:       skills.DependencyDirection{},
		Description: "A constructor takes a service of another self-registered module by its concrete class instead of an interface",
		Rule:        "Code talks to an interface wherever a seam fits: a service from another module is reached through an interface, never its concrete class.",
		Suggestion:  "Type the parameter on an interface the service implements, so this module depends on the contract and not on how the other module fills it.",
	}
}
