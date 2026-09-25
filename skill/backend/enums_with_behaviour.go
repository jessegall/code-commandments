package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed enums_with_behaviour.intro.md
	enumsWithBehaviourIntro string
	//go:embed enums_with_behaviour.principle.md
	enumsWithBehaviourPrinciple string
)

// EnumsWithBehaviour teaches: a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).
type EnumsWithBehaviour struct{}

func init() {
	skill.Register(catalog.Backend, EnumsWithBehaviour{})
}

// Definition is what the skill states about itself.
func (EnumsWithBehaviour) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/enums-with-behaviour",
		Tier:      skill.KeepInMind,
		Order:     9,
		Title:     "Enums with behaviour — seal the set, put the logic on the type",
		Trigger:   "How a closed set of values is modelled — a native backed enum (never raw strings or a const class), with the knowledge keyed off its cases living ON the enum as methods, not re-inlined as a `match`/`switch` at every call site. Read this BEFORE you write a fixed set of string/int values, a `match`/`switch` over an enum (or over strings that mirror one), a `const` class of scalars, or a string field whose values are a closed set.",
		Intro:     enumsWithBehaviourIntro,
		Summary:   "a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).",
		Principle: enumsWithBehaviourPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/value-objects", Note: `an enum is the closed-set member of "give data a type"; reach for it when the type's values are a fixed set.`},
			{Slug: "backend/absence", Note: "a missing/unhandled case is a throw, not a silent `default`."},
			{Slug: "backend/exceptions", Note: "a missing/unhandled case is a throw, not a silent `default`."},
			{Slug: "backend/fix-at-the-source", Note: `seal the set where the value is born (a typed enum field) so downstream code never re-parses a string.`},
		},
	}
}
