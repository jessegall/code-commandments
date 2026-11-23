package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed documentation.md
var documentationBody string

// Documentation teaches documenting little, in every language: a comment says what the code cannot, in the present tense, and nothing the code already says.
type Documentation struct{}

func init() {
	skill.Register(catalog.Shared, Documentation{})
}

func (Documentation) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/documentation",
		Tier:      skill.Mandatory,
		Order:     5,
		Title:     "Documentation — say only what the code cannot",
		Trigger:   "How to document, and mostly NOT to. A doc comment is one or two lines (three at most), present tense, about the code as it is NOW; an inline comment is rare and only explains a non-obvious *why*; never narrate the past or a change (\"previously…\", \"used to…\", \"now we…\", \"refactored to…\"). Read this the MOMENT you are about to write a doc comment, an inline comment, a class or method description, or an annotation that repeats what is already there, and when a documentation finding points here.",
		Summary:   "document little and only what the code cannot say: a one- or two-line doc comment in the present tense, a rare inline comment for a non-obvious why, never a restated line, a signature repeated in tags, a history, or a reference to something that is gone.",
		Body:      documentationBody,
		Formerly:  []string{"php/documentation", "csharp/documentation", "python/documentation", "shared/arguments"},
		Languages: languages,
	}
}
