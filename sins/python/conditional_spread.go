package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ConditionalSpread is `**({"k": v} if v else {})` / `*([x] if x else [])` — an entry is spread in only when present, using a conditional that turns absence into an empty collection.
type ConditionalSpread struct{}

func init() {
	sins.Register(catalog.Python, ConditionalSpread{})
}

// Definition is what the sin states about itself.
func (ConditionalSpread) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-conditional-spread",
		Skill:       skills.Absence{},
		Description: "`**({\"k\": v} if v else {})` / `*([x] if x else [])` — an entry is spread in only when present, using a conditional that turns absence into an empty collection.",
		Rule:        "Don't spread a conditional into an empty collection to include an entry; give the target a factory that drops what is absent, and pass the value by name.",
		Suggestion:  "A `@classmethod` factory — `Payload.of(note=note)` — whose body drops `None` keyword arguments, so an absent value simply vanishes with no conditional.",
	}
}
