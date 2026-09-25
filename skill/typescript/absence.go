package typescript

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.TypeScript, Absence{})
}

//go:embed text/absence/intro.md
var absenceIntro string

//go:embed text/absence/principle.md
var absencePrinciple string

// Absence teaches: model absence honestly — one spelling for missing, no `??` that invents a value, no `?.` on something always set.
type Absence struct{}

func (Absence) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "typescript/absence",
		Tier:      skill.KeepInMind,
		Order:     26,
		Title:     "TypeScript absence — say what is missing, and mean it",
		Trigger:   "Modelling a value that might not be there in TypeScript — a `?? default`, an `?.` chain, a `field?: T`, a `=== null` or `=== undefined` test, or deciding between `field?: T`, `T | null` and `T | undefined` on an interface. Read this BEFORE writing any of them in a .ts module or a component's script. TypeScript has TWO ways to be missing where PHP has one, and a type that admits absence the design never has is a lie the compiler will not catch. It is about the absence YOUR types declare, not about silencing the compiler on a DOM or library call that genuinely returns null.",
		Intro:     absenceIntro,
		Summary:   "model absence honestly — one spelling for missing, no `??` that invents a value, no `?.` on something always set.",
		Principle: absencePrinciple,
		Languages: []string{"ts"},
		Related: []skill.Relation{
			{Slug: "backend/absence", Note: "the same instinct on the server, with the tools PHP has and TypeScript does not."},
			{Slug: "backend/type-honesty", Note: "the general rule this serves: a type must not claim an optionality the design doesn't have."},
		},
	}
}
