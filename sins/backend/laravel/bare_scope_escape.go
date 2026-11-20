package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// BareScopeEscape is the bare-scope-escape sin.
type BareScopeEscape struct{}

func init() { sins.Register(catalog.PHP, BareScopeEscape{}) }

func (BareScopeEscape) Definition() sins.Definition {
	return sins.Definition{
		Name:        "bare-scope-escape",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "an Eloquent query drops every global scope, including ones added later",
		Rule:        "Name the scope a query steps out of (`withoutGlobalScope(OwnerScope::class)`), or pass whose rows it reads as an argument; never drop every global scope at once.",
		Suggestion:  "Name the one scope to drop, or give the query the owner it reads for explicitly.",
		Requires:    requiresLaravel,
	}
}
