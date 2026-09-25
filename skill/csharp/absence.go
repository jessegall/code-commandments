package csharp

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

// Absence teaches: decide absence where the value is born — throw, return an empty collection, or a Null Object — with nullable reference types saying honestly what may be missing; never `?? ""` a required value, never `!` to silence the compiler.
type Absence struct{}

func init() {
	skill.Register(catalog.CSharp, Absence{})
}

// Definition is what the skill states about itself.
func (Absence) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/absence",
		Tier:      skill.Mandatory,
		Order:     32,
		Title:     "C# absence — decide \"missing\" where the value is born",
		Trigger:   "Modelling a value that might not be there in C# — a return typed `T?`, a `return null` for \"not found\", an `is null` check or a `?? default` at a call site, a `FirstOrDefault()`, a `TryGetValue`, the null-forgiving `!`, or deciding between throwing, returning an empty collection and returning `null`. Read this BEFORE writing any of them.",
		Intro:     absenceIntro,
		Summary:   "decide absence where the value is born — throw, return an empty collection, or a Null Object — with nullable reference types saying honestly what may be missing; never `?? \"\"` a required value, never `!` to silence the compiler.",
		Principle: absencePrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/absence", Note: "the same decision on the PHP backend, with `Option`."},
			{Slug: "python/absence", Note: "the same decision in Python."},
			{Slug: "csharp/exceptions", Note: "when \"missing\" is a broken state, the named exception to throw."},
		},
	}
}
