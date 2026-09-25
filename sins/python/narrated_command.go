package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NarratedCommand is a command named in the third person — `hides()`, `locks_for_night()` — where a call is an order, not a description of one.
type NarratedCommand struct{}

func init() {
	sins.Register(catalog.Python, NarratedCommand{})
}

// Definition is what the sin states about itself.
func (NarratedCommand) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-narrated-command",
		Skill:       skills.MethodMood{},
		Description: "a command named in the third person — `hides()`, `locks_for_night()` — where a call is an order, not a description of one",
		Rule:        "Name a command in the imperative: `hide()`, `lock_for_night()`, `open_for(user)` — never the third-person `hides()`.",
		Suggestion:  "Drop the -s: the call site is giving the order, not narrating it.",
	}
}
