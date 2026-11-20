package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

// Documentation teaches how to write comments that explain the code.
type Documentation struct{}

func init() {
	skill.Register(catalog.Shared, Documentation{})
}

func (Documentation) Definition() skill.Definition {
	languages := make([]string, 0, len(contract.Languages))
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/documentation",
		Tier:      skill.Mandatory,
		Order:     5,
		Title:     "Documentation: explain the code as it is",
		Trigger:   "Read before writing a comment or changing a declaration's documentation.",
		Intro:     "A useful comment tells the reader why the code takes its shape or what contract a caller can rely on.",
		Summary:   "Explain present behavior, keep one short doc comment, and keep its references valid.",
		Principle: "Use names and types for what the code already says. Keep decisions and their history in the change record, and give any explanation that belongs in the source one place near its declaration.",
		Languages: languages,
	}
}
