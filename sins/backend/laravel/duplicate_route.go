package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// DuplicateRoute is the duplicate-route sin.
type DuplicateRoute struct{}

func init() { sins.Register(catalog.Backend, DuplicateRoute{}) }

// Definition is what the sin states about itself.
func (DuplicateRoute) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicate-route",
		Skill:       laravelskills.RouteActions{},
		Description: `Two route registrations of the same verb bind different URLs to the SAME ` + "`" + `[Controller, method]` + "`" + ` — two names for one handler (invokable single-action controllers, commonly aliased to several canonical URLs, are exempt)`,
		Rule:        `Register a ` + "`" + `[Controller, method]` + "`" + ` action once; a second URL onto the same handler is a maintenance trap (names, middleware, constraints drift). An invokable controller mapped to several canonical URLs is fine.`,
		Suggestion:  "Keep one route; if a second URL is truly needed, make it a redirect, or an invokable controller.",
		Requires:    requiresLaravel,
	}
}
