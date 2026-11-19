package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// HardCodedRoutePath is the hard-coded-route-path sin.
type HardCodedRoutePath struct{}

func init() { sins.Register(catalog.PHP, HardCodedRoutePath{}) }

func (HardCodedRoutePath) Definition() sins.Definition {
	return sins.Definition{
		Name:        "hard-coded-route-path",
		Skill:       skills.LaravelIdioms{},
		Description: "A path written out by hand where a named route already declares it",
		Rule:        "A route's path is declared once, in its registration; everywhere else builds it from the route's name.",
		Suggestion:  "Build the URL with `route('name', $parameters)` so a changed path changes every link with it.",
		Requires:    requiresLaravel,
	}
}
