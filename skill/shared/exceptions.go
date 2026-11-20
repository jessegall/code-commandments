package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed exceptions.intro.md
	exceptionsIntro string
	//go:embed exceptions.principle.md
	exceptionsPrinciple string
)

// Exceptions teaches how a failure is raised and handled in every language: loud, named, and with its cause.
type Exceptions struct{}

func init() {
	skill.Register(catalog.Shared, Exceptions{})
}

func (Exceptions) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/exceptions",
		Tier:      skill.Mandatory,
		Order:     3,
		Title:     "Exceptions — fail loud, named, with the cause",
		Trigger:   "Writing a `throw`/`raise`, a `try`/`catch`/`except`, or an exception class in any language. Read this BEFORE an empty catch, a catch that returns null or rethrows what it caught, a new failure thrown inside a catch, or `throw new Exception(\"…\")`, and when an exceptions finding points here.",
		Intro:     exceptionsIntro,
		Summary:   "never swallow a failure, catch only to do something, hand a wrapped failure its cause, and throw a named failure that writes its own message.",
		Principle: exceptionsPrinciple,
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "php/exceptions", Note: "what is particular to PHP's exceptions."},
			{Slug: "python/exceptions", Note: "what is particular to Python's exceptions."},
			{Slug: "csharp/exceptions", Note: "what is particular to C#'s exceptions."},
		},
	}
}
