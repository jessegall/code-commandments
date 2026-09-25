package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// FacadeCall is the facade-call sin.
type FacadeCall struct{}

func init() { sins.Register(catalog.Backend, FacadeCall{}) }

// Definition is what the sin states about itself.
func (FacadeCall) Definition() sins.Definition {
	return sins.Definition{
		Name:        "facade-call",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "Laravel facade call (`Cache::`, `Log::`, `Mail::` …)",
		Rule:        "Inject the dependency; never call a Laravel facade (`Cache::`, `Log::`, `Mail::`) inside a class.",
		Suggestion:  "Constructor-inject the dependency behind its interface.",
		Requires:    requiresLaravel,
	}
}
