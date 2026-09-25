package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// ModelMutationAtCallSite is the model-mutation-at-call-site sin.
type ModelMutationAtCallSite struct{}

func init() { sins.Register(catalog.Backend, ModelMutationAtCallSite{}) }

// Definition is what the sin states about itself.
func (ModelMutationAtCallSite) Definition() sins.Definition {
	return sins.Definition{
		Name:        "model-mutation-at-call-site",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "Set-property-then-`save()` at a call site (should be an intention method)",
		Rule:        "Mutate a model through an intention method; don't set-property-then-`save()` at a call site.",
		Suggestion:  "An intention method on the model (`$order->suspend($reason)`).",
		Requires:    requiresLaravel,
	}
}
