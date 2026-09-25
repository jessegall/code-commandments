package backend

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

// FixAtTheSource teaches: the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made.
type FixAtTheSource struct{}

func init() {
	skill.Register(catalog.Backend, FixAtTheSource{})
}

// Definition is what the skill states about itself.
func (FixAtTheSource) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/fix-at-the-source",
		Tier:      skill.Mandatory,
		Order:     1,
		Title:     "Fix at the source",
		Trigger:   "Find the root cause before changing anything — trace a value to where it is born and fix it THERE, never patch the symptom. Read this FIRST whenever you are asked to refactor, fix, clean up, improve, or review any code, file, class, or namespace — and specifically before adding a null check, a `?? default`, making a field nullable, absolving a finding, tolerating bad input downstream, or when the same value gets re-checked in many places. The root-cause-first move every other style rule defers to.",
		Intro:     fixAtTheSourceIntro,
		Summary:   `the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made.`,
		Principle: fixAtTheSourcePrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/absence", Note: "deciding absence at the producer is this rule applied to the null channel."},
			{Slug: "backend/exceptions", Note: "surfacing a failure where it's born is this rule applied to the error channel."},
			{Slug: "backend/value-objects", Note: "introducing the type at the source, not threading loose data downstream."},
		},
	}
}
