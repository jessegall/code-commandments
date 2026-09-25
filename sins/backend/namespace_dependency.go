package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NamespaceDependency is the namespace-dependency sin.
type NamespaceDependency struct{}

func init() { sins.Register(catalog.Backend, NamespaceDependency{}) }

// Definition is what the sin states about itself.
func (NamespaceDependency) Definition() sins.Definition {
	return sins.Definition{
		Name:        "namespace-dependency",
		Skill:       skills.DependencyDirection{},
		Description: "a declared layer references a layer it may not use (the arrow points back up)",
		Rule:        `Reference only DOWN the declared stack — a layer may use the layers it declared in ` + "`" + `mayUse` + "`" + `, and nothing else that is declared.`,
		Suggestion:  "invert the arrow: take the value/contract the low layer needs, and let the high layer supply it",
	}
}
