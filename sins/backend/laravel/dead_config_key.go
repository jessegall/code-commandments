package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// DeadConfigKey is the dead-config-key sin.
type DeadConfigKey struct{}

func init() { sins.Register(catalog.Backend, DeadConfigKey{}) }

// Definition is what the sin states about itself.
func (DeadConfigKey) Definition() sins.Definition {
	return sins.Definition{
		Name:        "dead-config-key",
		Skill:       laravelskills.LaravelIdioms{},
		Description: `A config key nothing reads — dead surface left behind by a deleted feature, which new code may wrongly adopt`,
		Rule:        `Config is an interface: every key exists because something reads it. When the last reader goes, the key goes with it.`,
		Suggestion:  "Delete the key (and its env var), or restore the reader the feature lost.",
		Requires:    requiresLaravel,
	}
}
