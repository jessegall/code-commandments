package python

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

// Templates teaches: a multi-line string is a triple-quoted f-string that SHOWS its output, never a list of line fragments joined.
type Templates struct{}

func init() {
	skill.Register(catalog.Python, Templates{})
}

// Definition is what the skill states about itself.
func (Templates) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/templates",
		Tier:      skill.KeepInMind,
		Order:     40,
		Title:     "Python templates — state the shape, don't assemble it",
		Trigger:   "Writing a multi-line string a Python program emits — generated code, a config block, a report, a message. Read this the moment you find yourself building a list of lines and `\"\\n\".join(...)`-ing it, so the OUTPUT stays readable in the source that produces it.",
		Intro:     templatesIntro,
		Summary:   "a multi-line string is a triple-quoted f-string that SHOWS its output, never a list of line fragments joined.",
		Principle: templatesPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/templates", Note: "the same discipline with PHP heredocs."},
		},
	}
}
