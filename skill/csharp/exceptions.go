package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed exceptions.intro.md
	exceptionsIntro string
	//go:embed exceptions.principle.md
	exceptionsPrinciple string
)

// Exceptions teaches: throw named exceptions built by a static factory, never swallow a failure, and keep the cause as the inner exception.
type Exceptions struct{}

func init() {
	skill.Register(catalog.CSharp, Exceptions{})
}

// Definition is what the skill states about itself.
func (Exceptions) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/exceptions",
		Tier:      skill.KeepInMind,
		Order:     31,
		Title:     "C# exceptions — fail loud, named, at the source",
		Trigger:   "Writing a `throw`, a `try`/`catch`, or an exception class in C# — or deciding what a method does when something goes wrong. Read this BEFORE you write an empty `catch`, a `catch` that returns `null`/`default`/an empty list, `throw new InvalidOperationException(\"…\")` with a message built at the throw, or a rethrow that drops the original exception.",
		Intro:     exceptionsIntro,
		Summary:   "throw named exceptions built by a static factory, never swallow a failure, and keep the cause as the inner exception.",
		Principle: exceptionsPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/exceptions", Note: "the same discipline on the PHP backend, with `::for()` factories."},
			{Slug: "python/exceptions", Note: "the same discipline in Python, with classmethod factories."},
			{Slug: "backend/absence", Note: "whether \"missing\" is a failure to throw at all, or a value to model."},
		},
	}
}
