package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// DeadEventWiring is the dead-event-wiring sin.
type DeadEventWiring struct{}

func init() { sins.Register(catalog.Backend, DeadEventWiring{}) }

// Definition is what the sin states about itself.
func (DeadEventWiring) Definition() sins.Definition {
	return sins.Definition{
		Name:        "dead-event-wiring",
		Skill:       laravelskills.LaravelIdioms{},
		Description: `An ` + "`" + `Event::listen` + "`" + ` on an event class no live code path can fire — a listener chain that dead-ends but reads as live wiring`,
		Rule:        "A listener exists to answer a dispatch. When the last dispatcher goes, the listener goes with it.",
		Suggestion:  `Delete the registration (and the listener, if nothing else reaches it) — or restore the dispatch the listener was waiting for.`,
		Requires:    requiresLaravel,
	}
}
