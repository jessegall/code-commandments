package python

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

// Exceptions teaches: raise named exceptions built by a classmethod factory, never swallow a failure, and keep the cause with `raise … from`.
type Exceptions struct{}

func init() {
	skill.Register(catalog.Python, Exceptions{})
}

// Definition is what the skill states about itself.
func (Exceptions) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/exceptions",
		Tier:      skill.KeepInMind,
		Order:     30,
		Title:     "Python exceptions — fail loud, named, at the source",
		Trigger:   "Writing a `raise`, a `try`/`except`, or an exception class in Python — or deciding what a function does when something goes wrong. Read this BEFORE you write `except Exception: pass`, an `except` that returns `None`/`[]`/`False`, `raise ValueError(\"…\")` with a message built at the raise, or a `raise` inside an `except` without `from`.",
		Intro:     exceptionsIntro,
		Summary:   "raise named exceptions built by a classmethod factory, never swallow a failure, and keep the cause with `raise … from`.",
		Principle: exceptionsPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/exceptions", Note: "the same discipline on the PHP backend, with `::for()` factories."},
			{Slug: "backend/absence", Note: "whether \"missing\" is a failure to raise at all, or a value to model."},
		},
	}
}
