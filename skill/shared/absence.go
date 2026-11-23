package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed absence.md
	absenceBody string
)

// Absence teaches how a value that might be missing is modelled in every language: decided once, where the value is born, and said in the type.
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
		Title:     "Absence — decide it where the value is born",
		Trigger:   "Modelling a value that might not be there, in any language: a nullable return or field (`?T`, `T | null`, `X | None`), a `return null`, a fallback (`??`, `or`, `.get(k, default)`), a `?.`, a blank string or empty collection standing in for missing, an optional callback, or an \"is it there?\" check. Read this BEFORE writing any of them, and when an absence finding points here.",
		Summary:   "decide absence where the value is born (throw by name, return an empty collection, give a default that does nothing, or say it in the type) so no caller treats a nullable as present or reads a fake value as data.",
		Body:      absenceBody,
		Formerly:  []string{"php/absence", "csharp/absence", "python/absence", "typescript/absence"},
		Languages: languages,
	}
}
