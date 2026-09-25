package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// ContainerReach is the container-reach sin.
type ContainerReach struct{}

func init() { sins.Register(catalog.Backend, ContainerReach{}) }

// Definition is what the sin states about itself.
func (ContainerReach) Definition() sins.Definition {
	return sins.Definition{
		Name:        "container-reach",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "`app()`/`resolve()` reach inside a container-resolved class",
		Rule:        `Declare dependencies in the constructor; never reach into the container with ` + "`" + `app()` + "`" + `/` + "`" + `resolve()` + "`" + ` from a resolved class.`,
		Suggestion:  "Declare the dependency as a constructor parameter.",
		Requires:    requiresLaravel,
	}
}
