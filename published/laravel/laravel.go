// Package laravel publishes what a Laravel backend's router declares for the frontend's detectors: the path of every named route.
package laravel

import (
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/published"
)

func init() {
	published.Register(Routes{})
}

// Routes publishes the paths of the codebase's named routes.
type Routes struct{}

func (Routes) Contracts(codebase *engine.Codebase) []published.Contract {
	return []published.Contract{published.ServerRoutes{Matches: laravelnode.RoutePathsOf(codebase).Matches}}
}
