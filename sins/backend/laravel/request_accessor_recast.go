package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// RequestAccessorRecast is the request-accessor-recast sin.
type RequestAccessorRecast struct{}

func init() { sins.Register(catalog.Backend, RequestAccessorRecast{}) }

// Definition is what the sin states about itself.
func (RequestAccessorRecast) Definition() sins.Definition {
	return sins.Definition{
		Name:        "request-accessor-recast",
		Skill:       laravelskills.LaravelIdioms{},
		Description: `Re-coercing a typed request accessor at a call site — ` + "`" + `$request->string('id')->toString()` + "`" + ` or ` + "`" + `(string) $request->string('id')` + "`" + ` instead of a named getter on a request class`,
		Rule:        `Expose a named getter on a typed request class; don't re-coerce a typed accessor (` + "`" + `$request->string('id')->toString()` + "`" + `) at a call site.`,
		Suggestion:  "A named getter on a typed request class returning the coerced value.",
		Requires:    requiresLaravel,
	}
}
