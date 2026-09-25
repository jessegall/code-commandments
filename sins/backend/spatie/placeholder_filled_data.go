package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// PlaceholderFilledData is the placeholder-filled-data sin.
type PlaceholderFilledData struct{}

func init() { sins.Register(catalog.Backend, PlaceholderFilledData{}) }

// Definition is what the sin states about itself.
func (PlaceholderFilledData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "placeholder-filled-data",
		Skill:       skills.TypeHonesty{},
		Description: `A required non-nullable ` + "`" + `string` + "`" + ` slot handed ` + "`" + `''` + "`" + ` — the type promises a value that is always there and the caller has none`,
		Rule:        `A required slot means the caller has the value. Filling it just to satisfy the type signature hides a missing value in a way no type check can catch.`,
		Suggestion:  "Fetch the real value, or split off a narrower type that only promises the fields you actually have.",
	}
}
