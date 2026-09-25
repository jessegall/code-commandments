package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed exceptions.intro.md
	exceptionsIntro string
	//go:embed exceptions.principle.md
	exceptionsPrinciple string
)

// Exceptions teaches: throwing or catching: named `::for()` factory exceptions, never swallow a failure.
type Exceptions struct{}

func init() {
	skill.Register(catalog.Backend, Exceptions{})
}

// Definition is what the skill states about itself.
func (Exceptions) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/exceptions",
		Tier:      skill.KeepInMind,
		Order:     8,
		Title:     "Exceptions — fail hard, fix once",
		Trigger:   "How to fail — throw NAMED exceptions via static factories (`Thing::for($x)`), never a message string at the throw site, and never swallow a failure into null/false/[]/Option::none(). Read this FIRST whenever you write a `throw`, a `try`/`catch`, an exception class, or are deciding what to do when something goes wrong. Fail hard and named, at the source.",
		Intro:     exceptionsIntro,
		Summary:   "throwing or catching: named `::for()` factory exceptions, never swallow a failure.",
		Principle: exceptionsPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "surface the failure where it's born."},
			{Slug: "backend/absence", Note: `absence routes "missing = broken state" here for the *how* of throwing; this skill routes "swallowed failure became an empty value" back there as the inverse smell.`},
		},
	}
}
