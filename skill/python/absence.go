package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed absence.intro.md
	absenceIntro string
	//go:embed absence.principle.md
	absencePrinciple string
)

// Absence teaches: decide absence where the value is born — raise, return an empty collection, or a Null Object — instead of an `X | None` every caller re-checks; never `or ""` a required value.
type Absence struct{}

func init() {
	skill.Register(catalog.Python, Absence{})
}

// Definition is what the skill states about itself.
func (Absence) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/absence",
		Tier:      skill.Mandatory,
		Order:     31,
		Title:     "Python absence — decide \"missing\" where the value is born",
		Trigger:   "Modelling a value that might not be there in Python — a return typed `X | None` or `Optional[X]`, a `return None` for \"not found\", an `if x is None:` or `x or default` at a call site, a `.get(key, default)`, or deciding between raising, returning an empty collection and returning `None`. Read this BEFORE writing any of them.",
		Intro:     absenceIntro,
		Summary:   "decide absence where the value is born — raise, return an empty collection, or a Null Object — instead of an `X | None` every caller re-checks; never `or \"\"` a required value.",
		Principle: absencePrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/absence", Note: "the same decision on the PHP backend, with `Option`."},
			{Slug: "python/exceptions", Note: "when \"missing\" is a broken state, the named exception to raise."},
		},
	}
}
