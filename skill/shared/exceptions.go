package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed exceptions.md
	exceptionsBody string
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
		Summary:   "never swallow a failure, catch only to do something, hand a wrapped failure its cause, and throw a named failure that writes its own message.",
		Body:      exceptionsBody,
		Formerly:  []string{"php/exceptions", "csharp/exceptions", "python/exceptions"},
		Languages: languages,
	}
}
