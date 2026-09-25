package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DeNulledFinder is the de-nulled-finder sin.
type DeNulledFinder struct{}

func init() { sins.Register(catalog.Backend, DeNulledFinder{}) }

// Definition is what the sin states about itself.
func (DeNulledFinder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "de-nulled-finder",
		Skill:       skills.Absence{},
		Description: `A finder that returns ` + "`" + `null` + "`" + ` for both "missing" and "broken" instead of throwing — the kind of ` + "`" + `?T` + "`" + ` finder whose callers all end up de-nulling it.`,
		Rule:        `Decide absence where the value is found — if every caller ends up de-nulling a ` + "`" + `?T` + "`" + ` finder, make it return a definite type instead (throw, ` + "`" + `Option` + "`" + `, or empty).`,
		Suggestion:  "Add a resolve-or-throw `get()` beside `find()`, or return `Option<T>`.",
	}
}
