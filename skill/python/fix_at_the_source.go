package python

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

// FixAtTheSource teaches: trace a value, an effect or a piece of state to where it starts, and fix it there.
type FixAtTheSource struct{}

func init() {
	skill.Register(catalog.Python, FixAtTheSource{})
}

// Definition is what the skill states about itself.
func (FixAtTheSource) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/fix-at-the-source",
		Tier:      skill.Mandatory,
		Order:     34,
		Title:     "Python fix at the source — where a value is born, not where it hurts",
		Trigger:   "Writing an `__init__` that calls out to something it was handed, a module-level or class-level variable that functions write to, or a fix for a Python finding that is tempting to patch where it surfaced. Read this BEFORE making a constructor do work, keeping state in a `global` or a class attribute, or adding a check at a call site, and when a `python-constructor-side-effect` or `python-mutable-static-state` finding points here.",
		Intro:     fixAtTheSourceIntro,
		Summary:   "trace a value, an effect or a piece of state to where it starts, and fix it there.",
		Principle: fixAtTheSourcePrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "the same discipline over PHP, where every other skill defers to it."},
			{Slug: "python/absence", Note: "deciding absence where a value is born is this rule applied to `None`."},
		},
	}
}
