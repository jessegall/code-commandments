package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed templates.md
var templatesBody string

// Templates teaches writing a fixed block of text as one multi-line literal in every language, instead of assembling it from fragments.
type Templates struct{}

func init() {
	skill.Register(catalog.Shared, Templates{})
}

func (Templates) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/templates",
		Tier:      skill.KeepInMind,
		Order:     14,
		Title:     "Templates — state the shape, do not assemble it",
		Trigger:   "Building a multi-line string in code: a generated file, a message, a query, a block of markup, put together from a list of lines joined by a newline or from concatenated fragments. Read this BEFORE you write the first fragment, and when a templates finding points here.",
		Summary:   "write a fixed multi-line text as one multi-line literal at its real indentation and interpolate only what varies; never a list of line fragments joined by a newline.",
		Body:      templatesBody,
		Formerly:  []string{"php/templates", "csharp/templates", "python/templates"},
		Languages: languages,
	}
}
