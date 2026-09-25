package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// ConfigRead is the config-read sin.
type ConfigRead struct{}

func init() { sins.Register(catalog.Backend, ConfigRead{}) }

// Definition is what the sin states about itself.
func (ConfigRead) Definition() sins.Definition {
	return sins.Definition{
		Name:        "config-read",
		Skill:       laravelskills.LaravelIdioms{},
		Description: "`config('…')` read inside a class",
		Rule:        "Inject a typed config object; never read `config('…')` inside a class.",
		Suggestion:  "Inject a typed config value object.",
		Requires:    requiresLaravel,
	}
}
