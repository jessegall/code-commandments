package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// MassUpdateAtCallSite is the mass-update-at-call-site sin.
type MassUpdateAtCallSite struct{}

func init() { sins.Register(catalog.Backend, MassUpdateAtCallSite{}) }

// Definition is what the sin states about itself.
func (MassUpdateAtCallSite) Definition() sins.Definition {
	return sins.Definition{
		Name:        "mass-update-at-call-site",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "Bare `$model->update([...])` mass-array update at a call site",
		Rule:        `Mutate a model through an intention method; never ` + "`" + `$model->update([...])` + "`" + ` an anonymous array of columns at a call site.`,
		Suggestion:  "An intention method on the model (`$order->markPaid()`).",
		Requires:    requiresLaravel,
	}
}
