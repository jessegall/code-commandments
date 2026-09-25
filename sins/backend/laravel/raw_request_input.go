package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// RawRequestInput is the raw-request-input sin.
type RawRequestInput struct{}

func init() { sins.Register(catalog.Backend, RawRequestInput{}) }

// Definition is what the sin states about itself.
func (RawRequestInput) Definition() sins.Definition {
	return sins.Definition{
		Name:        "raw-request-input",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "Raw `->input()/->get()/->query()/->post()` on a Request",
		Rule:        `Read request input through a typed accessor (` + "`" + `$request->string('x')` + "`" + `); never raw ` + "`" + `->input()` + "`" + `/` + "`" + `->get()` + "`" + `/` + "`" + `->query()` + "`" + `.`,
		Suggestion:  "A named getter on a `FormRequest` subclass (`$request->productId()`).",
		Requires:    requiresLaravel,
	}
}
