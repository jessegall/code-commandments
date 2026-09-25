package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// OrphanedBinding is the orphaned-binding sin.
type OrphanedBinding struct{}

func init() { sins.Register(catalog.Backend, OrphanedBinding{}) }

// Definition is what the sin states about itself.
func (OrphanedBinding) Definition() sins.Definition {
	return sins.Definition{
		Name:        "orphaned-binding",
		Skill:       laravelskills.LaravelIdioms{},
		Description: `A container binding whose abstract nothing ever resolves — dead wiring that reads as load-bearing and survives every refactor`,
		Rule:        `Wiring is code: a binding exists to answer a resolve. When the last consumer goes, the binding goes with it.`,
		Suggestion:  "Delete the registration (and the implementation it names, if that is dead too).",
		Requires:    requiresLaravel,
	}
}
