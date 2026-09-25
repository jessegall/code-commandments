package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// RouteDelegatesToController is the route-delegates-to-controller sin.
type RouteDelegatesToController struct{}

func init() { sins.Register(catalog.Backend, RouteDelegatesToController{}) }

// Definition is what the sin states about itself.
func (RouteDelegatesToController) Definition() sins.Definition {
	return sins.Definition{
		Name:        "route-delegates-to-controller",
		Skill:       laravelskills.RouteActions{},
		Description: `A route action forwards to ANOTHER controller's action (` + "`" + `return $this->otherController->action(...)` + "`" + `) — a redundant entry point onto an operation that already has one`,
		Rule:        `A route action delegates INTO the domain (a service/action class), never sideways into another controller.`,
		Suggestion:  `Extract the shared work into a service both routes call, or point the route at the real action and delete the wrapper.`,
		Requires:    requiresLaravel,
	}
}
