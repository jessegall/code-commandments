package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed fix_at_the_source.intro.md
	fixAtTheSourceIntro string
	//go:embed fix_at_the_source.principle.md
	fixAtTheSourcePrinciple string
)

// FixAtTheSource teaches: trace a value, an effect or a piece of state back to where it starts, and fix it there.
type FixAtTheSource struct{}

func init() {
	skill.Register(catalog.CSharp, FixAtTheSource{})
}

// Definition is what the skill states about itself.
func (FixAtTheSource) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/fix-at-the-source",
		Tier:      skill.Mandatory,
		Order:     35,
		Title:     "C# fix at the source — fix a value where it is made, not where it breaks",
		Trigger:   "Writing a C# constructor that calls a method on something it was handed, a `static` field that methods write to, or a fix for a C# finding that is tempting to patch where it showed up. Read this BEFORE making a constructor do work, keeping state in a static field, or adding a check at a call site, and when a `csharp-constructor-side-effect` or `csharp-mutable-static-state` finding points here.",
		Intro:     fixAtTheSourceIntro,
		Summary:   "trace a value, an effect or a piece of state back to where it starts, and fix it there.",
		Principle: fixAtTheSourcePrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "the same discipline in PHP, which every other skill builds on."},
			{Slug: "csharp/absence", Note: "deciding what \"missing\" means where a value is made is this rule applied to `null`."},
		},
	}
}
