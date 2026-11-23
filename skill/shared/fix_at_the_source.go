package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed fix_at_the_source.md
var fixAtTheSourceBody string

// FixAtTheSource teaches the move every other skill defers to, in every language: trace a problem to where it is born and fix it there.
type FixAtTheSource struct{}

func init() {
	skill.Register(catalog.Shared, FixAtTheSource{})
}

func (FixAtTheSource) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/fix-at-the-source",
		Tier:      skill.Mandatory,
		Order:     1,
		Title:     "Fix at the source",
		Trigger:   "Find the root cause before changing anything: trace a value to where it is born and fix it THERE, never patch the symptom. Read this FIRST whenever you are asked to refactor, fix, clean up, improve or review any code, in any language, and specifically before adding a null check, a `?? default`, a cast, a wrapper or a try/catch, making a field nullable, exempting a finding, tolerating bad input downstream, or when the same value gets re-checked in many places. Also before a constructor that does work, a static field that is written, or blocking on asynchronous work.",
		Summary:   "the root-cause-first move: trace a value to where it is born, fix it there and delete the symptom, never silence it. Governs how every change is made.",
		Body:      fixAtTheSourceBody,
		Formerly:  []string{"php/fix-at-the-source", "csharp/fix-at-the-source", "python/fix-at-the-source"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/absence", Note: "the four honest answers when a value really can be missing."},
			{Slug: "shared/type-honesty", Note: "a declaration that agrees with what the source produces."},
		},
	}
}
