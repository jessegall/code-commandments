package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed absence.intro.md
	absenceIntro string
	//go:embed absence.principle.md
	absencePrinciple string
)

// Absence teaches how a value that might be missing is asked about in every language: directly, once, and before the work starts.
type Absence struct{}

func init() {
	skill.Register(catalog.Shared, Absence{})
}

func (Absence) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/absence",
		Tier:      skill.Mandatory,
		Order:     2,
		Title:     "Absence — ask whether it is there, directly and once",
		Trigger:   "Writing a fallback for a value that might be missing in any language (`??`, `or`, `.get(k, default)`) and then comparing the result, or looping over `items ?? []` / `items or []`. Read this BEFORE writing either, and when an absence finding points here.",
		Intro:     absenceIntro,
		Summary:   "ask whether a value is there directly instead of comparing it to its own fallback, and guard against a missing collection before the loop instead of in its header.",
		Principle: absencePrinciple,
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "php/absence", Note: "what is particular to PHP's absence."},
			{Slug: "python/absence", Note: "what is particular to Python's absence."},
			{Slug: "typescript/absence", Note: "what is particular to TypeScript's absence."},
			{Slug: "csharp/absence", Note: "what is particular to C#'s absence."},
		},
	}
}
