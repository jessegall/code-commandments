package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// RequestAccessorRecast is the request-accessor-recast sin.
type RequestAccessorRecast struct{}

func init() { sins.Register(catalog.PHP, RequestAccessorRecast{}) }

func (RequestAccessorRecast) Definition() sins.Definition {
	return sins.Definition{
		Name:        "request-accessor-recast",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "Casting the Stringable returned by a typed request accessor instead of using its `toString()` method.",
		Rule:        "Use `toString()` to read a plain string from the typed request accessor.",
		Suggestion:  "Call `$request->string('id')->toString()` for the string value.",
		Requires:    requiresLaravel,
	}
}
