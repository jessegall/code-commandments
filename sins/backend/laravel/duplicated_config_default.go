package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// DuplicatedConfigDefault is the duplicated-config-default sin.
type DuplicatedConfigDefault struct{}

func init() { sins.Register(catalog.Backend, DuplicatedConfigDefault{}) }

// Definition is what the sin states about itself.
func (DuplicatedConfigDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicated-config-default",
		Skill:       laravelskills.LaravelIdioms{},
		Description: `A config key whose default is stated TWICE — once in the config file, again as the reader's inline fallback — two sources of truth that drift silently`,
		Rule:        `The config FILE owns the default. A reader asks for the value; it does not restate what the value should be when absent.`,
		Suggestion:  `Drop the reader's fallback and let the config file answer — or delete the key from the file and let the reader's default be the one truth.`,
		Requires:    requiresLaravel,
	}
}
