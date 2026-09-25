package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// DanglingRouteName is the dangling-route-name sin.
type DanglingRouteName struct{}

func init() { sins.Register(catalog.Backend, DanglingRouteName{}) }

// Definition is what the sin states about itself.
func (DanglingRouteName) Definition() sins.Definition {
	return sins.Definition{
		Name:        "dangling-route-name",
		Skill:       laravelskills.RouteActions{},
		Description: `A ` + "`" + `route('x')` + "`" + ` lookup naming a route no registration mints — a stringly cross-reference that only fails at runtime, as a 500`,
		Rule:        `The route-name vocabulary is a CLOSED set: every name looked up must be a name some route registers. Renaming a route means renaming its references in the same breath.`,
		Suggestion:  "Point the lookup at the registered name, or register the route the name promises.",
		Requires:    requiresLaravel,
	}
}
