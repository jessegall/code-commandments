package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NarratedCommand is the narrated-command sin.
type NarratedCommand struct{}

func init() { sins.Register(catalog.Backend, NarratedCommand{}) }

// Definition is what the sin states about itself.
func (NarratedCommand) Definition() sins.Definition {
	return sins.Definition{
		Name:        "narrated-command",
		Skill:       skills.MethodMood{},
		Description: `A command named in the third person — ` + "`" + `hides()` + "`" + `, ` + "`" + `entersTestMode()` + "`" + ` — where a call is an order, not a description of one`,
		Rule:        `Name a command in the imperative: ` + "`" + `hide()` + "`" + `, ` + "`" + `enterTestMode()` + "`" + `, ` + "`" + `openFor(\$user)` + "`" + ` — never the third-person ` + "`" + `hides()` + "`" + `, and never a participle.`,
		Suggestion:  "drop the -s: the call site is giving the order, not narrating it",
	}
}
