package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

// Dependencies teaches which modules an import may reach.
type Dependencies struct{}

func init() {
	skill.Register(catalog.Shared, Dependencies{})
}

func (Dependencies) Definition() skill.Definition {
	languages := make([]string, 0, len(contract.Languages))
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}
	return skill.Definition{
		Slug: "shared/dependencies", Tier: skill.Mandatory, Order: 9,
		Title:     "Dependencies: keep imports going in one direction",
		Trigger:   "Read before importing anything between modules or layers.",
		Intro:     "An import states which module a part of the project depends on.",
		Summary:   "Keep dependencies within each layer's declared boundary and avoid mutual imports between modules.",
		Principle: "When two modules need the same contract, give that contract a home both may import.",
		Languages: languages,
	}
}
