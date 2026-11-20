package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

// Duplication teaches where repeated code belongs.
type Duplication struct{}

func init() {
	skill.Register(catalog.Shared, Duplication{})
}

func (Duplication) Definition() skill.Definition {
	languages := make([]string, 0, len(contract.Languages))
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/duplication",
		Tier:      skill.Mandatory,
		Order:     4,
		Title:     "Duplication: give repeated code one owner",
		Trigger:   "Read before copying a function, a compound condition, or a path to the same folder.",
		Intro:     "Code that represents one decision, mechanism or folder should say what it means in one place.",
		Summary:   "Give each repeated decision, mechanism or path a name and a single owner.",
		Principle: "Put the shared code where its data or responsibility lives. Pass the values that differ, and have each caller ask the owner for the answer.",
		Languages: languages,
	}
}
