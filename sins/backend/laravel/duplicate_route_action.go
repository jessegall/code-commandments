package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// DuplicateRouteAction is the duplicate-route-action sin.
type DuplicateRouteAction struct{}

func init() { sins.Register(catalog.Backend, DuplicateRouteAction{}) }

// Definition is what the sin states about itself.
func (DuplicateRouteAction) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicate-route-action",
		Skill:       laravelskills.RouteActions{},
		Description: `Two route actions in different controllers thinly delegate to the SAME operation (` + "`" + `return $this->exporter->export(...)` + "`" + `) — the same entry point twice`,
		Rule:        `One operation, one entry point — collapse duplicate thin actions to a single action (or two routes onto one), with the work in the shared service.`,
		Suggestion:  "Delete the duplicate action and point its route at the surviving one (or a redirect).",
		Requires:    requiresLaravel,
	}
}
