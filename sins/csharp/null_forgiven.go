package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// NullForgiven is the null-forgiving `!` on a value declared nullable — the compiler told the caller it may be null, and `!` silences it instead of deciding.
type NullForgiven struct{}

func init() {
	sins.Register(catalog.CSharp, NullForgiven{})
}

// Definition is what the sin states about itself.
func (NullForgiven) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-null-forgiven",
		Skill:       skills.Absence{},
		Description: "The null-forgiving `!` on a value declared nullable — the compiler told the caller it may be null, and `!` silences it instead of deciding",
		Rule:        "Never silence a nullable warning with `!` — decide the missing case where the value is created, or handle it here.",
		Suggestion:  "Make the value non-nullable at its source, throw a named exception where it must exist, handle the null branch, or narrow it honestly (`OfType<T>()`, a pattern, `TryGetValue`).",
	}
}
