package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Vue, HardCodedServerURL{})
}

// HardCodedServerURL is the sin "hard-coded-server-url".
type HardCodedServerURL struct{}

func (HardCodedServerURL) Definition() sins.Definition {
	return sins.Definition{
		Name:        "hard-coded-server-url",
		Skill:       frontendskill.ServerCalls{},
		Description: "A string written out as the path of a route the server declares by name",
		Rule:        "Every server address comes from the generated routes, never a path written by hand.",
		Suggestion:  "Build it with the generated route helper (`route('plans.show', { plan: card.number })`) or the generated action's `.url()`.",
	}
}
