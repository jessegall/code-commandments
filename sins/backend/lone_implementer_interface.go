package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// LoneImplementerInterface is the lone-implementer-interface sin.
type LoneImplementerInterface struct{}

func init() { sins.Register(catalog.PHP, LoneImplementerInterface{}) }


func (LoneImplementerInterface) Definition() sins.Definition {
	return sins.Definition{
		Name:        "lone-implementer-interface",
		Skill:       skills.DependencyDirection{},
		Description: "An interface with one implementing class, named by nothing outside that class's own module: an indirection with one side",
		Rule:        "An interface earns its place as a port another module uses, or a seam with a second implementation; inside one module with one implementer, depend on the class.",
		Suggestion:  "Type against the class and delete the interface, or move the interface to the module that consumes it, where it becomes that module's port.",
	}
}
