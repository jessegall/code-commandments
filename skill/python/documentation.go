package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed documentation.intro.md
	documentationIntro string
	//go:embed documentation.principle.md
	documentationPrinciple string
)

// Documentation teaches: concise, present-tense docstrings; rare comments; never narrate the past.
type Documentation struct{}

func init() {
	skill.Register(catalog.Python, Documentation{})
}

// Definition is what the skill states about itself.
func (Documentation) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/documentation",
		Tier:      skill.Mandatory,
		Order:     42,
		Title:     "Python documentation — concise, present-tense, rare",
		Trigger:   "How to document Python, and mostly not to. A docstring is a line or two about the code as it is NOW; a `#` comment is rare and only explains a non-obvious *why*; never narrate the past or a change (\"previously…\", \"used to…\", \"refactored to…\"). Read this the moment you are about to write a docstring, a `#` comment, or an `Args:`/`Returns:` section.",
		Intro:     documentationIntro,
		Summary:   "concise, present-tense docstrings; rare comments; never narrate the past.",
		Principle: documentationPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/documentation", Note: "the same discipline for PHP docblocks."},
			{Slug: "python/fix-at-the-source", Note: "fix the shape instead of documenting the workaround."},
		},
	}
}
