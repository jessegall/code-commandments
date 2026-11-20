package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

// Enums teaches where closed sets and decisions about their cases belong.
type Enums struct{}

func init() {
	skill.Register(catalog.Shared, Enums{})
}

func (Enums) Definition() skill.Definition {
	languages := make([]string, 0, len(contract.Languages))
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}
	return skill.Definition{
		Slug: "shared/enums", Tier: skill.Mandatory, Order: 6,
		Title:     "Enums: give a closed set one source",
		Trigger:   "Read before repeating a declared set of cases in a switch, membership check, or chain of comparisons.",
		Intro:     "The declared type owns the names for a closed set of values.",
		Summary:   "Name the values once and keep common decisions about the cases together.",
		Principle: "Translate strings to the declared type at the boundary. Keep shared behavior keyed by the named cases, where a new case calls for one decision.",
		Languages: languages,
	}
}
