package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed templates.intro.md
	templatesIntro string
	//go:embed templates.principle.md
	templatesPrinciple string
)

// Templates teaches: a multi-line string is a heredoc that SHOWS its output, never a list of line fragments joined.
type Templates struct{}

func init() {
	skill.Register(catalog.Backend, Templates{})
}

// Definition is what the skill states about itself.
func (Templates) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/templates",
		Tier:      skill.KeepInMind,
		Order:     14,
		Title:     "Templates — state the shape, don't assemble it",
		Trigger:   `Writing a multi-line string a program emits — generated code, a config block, a report, a message. Read this the moment you find yourself building one line at a time and joining, so the OUTPUT stays readable in the source that produces it.`,
		Intro:     templatesIntro,
		Summary:   "a multi-line string is a heredoc that SHOWS its output, never a list of line fragments joined.",
		Principle: templatesPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/documentation", Note: "the same instinct one scale down: say a thing once, in the form a reader can check."},
			{Slug: "backend/value-objects", Note: "a shape worth stating repeatedly is usually a type, not a string."},
		},
	}
}
