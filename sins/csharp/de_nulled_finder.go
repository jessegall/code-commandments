package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// DeNulledFinder is a finder returning a nullable object whose every caller asserts it is there — `Find(id)!`, `Find(id) ?? throw …` — a miss the finder should have refused itself.
type DeNulledFinder struct{}

func init() {
	sins.Register(catalog.CSharp, DeNulledFinder{})
}

// Definition is what the sin states about itself.
func (DeNulledFinder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-de-nulled-finder",
		Skill:       skills.Absence{},
		Description: "a finder returning a nullable object whose every caller asserts it is there — `Find(id)!`, `Find(id) ?? throw …` — a miss the finder should have refused itself",
		Rule:        "Decide absence where the value is found — if every caller treats a `T?` finder's miss as impossible, give it a resolve-or-throw form instead of re-asserting at each call site.",
		Suggestion:  "Add a resolve-or-throw `Get(id)` beside `Find(id)` and call it where the callers de-null, or make the finder throw on a miss.",
	}
}
