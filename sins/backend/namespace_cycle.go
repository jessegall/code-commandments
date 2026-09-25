package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NamespaceCycle is the namespace-cycle sin.
type NamespaceCycle struct{}

func init() { sins.Register(catalog.Backend, NamespaceCycle{}) }

// Definition is what the sin states about itself.
func (NamespaceCycle) Definition() sins.Definition {
	return sins.Definition{
		Name:        "namespace-cycle",
		Skill:       skills.DependencyDirection{},
		Description: "two namespaces that reference each other — neither can be read, tested or moved alone",
		Rule:        `Break every namespace cycle — dependencies point ONE way, so a namespace can always be lifted out on its own.`,
		Suggestion:  `cut the weaker arrow: move the shared class down, or have the lower namespace own an interface the higher one implements`,
	}
}
