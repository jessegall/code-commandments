package backend

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

// Documentation teaches: concise, present-tense docs; rare inline comments; never narrate the past.
type Documentation struct{}

func init() {
	skill.Register(catalog.Backend, Documentation{})
}

// Definition is what the skill states about itself.
func (Documentation) Definition() skill.Definition {
	return skill.Definition{
		Slug:                  "backend/documentation",
		Tier:                  skill.Mandatory,
		Order:                 6,
		Title:                 "Documentation — concise, present-tense, rare",
		Trigger:               "How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change (\"previously…\", \"used to…\", \"now we…\", \"refactored to…\"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description.",
		Intro:                 documentationIntro,
		Summary:               "concise, present-tense docs; rare inline comments; never narrate the past.",
		Principle:             documentationPrinciple,
		ExamplesKeepDocblocks: true,
		Languages:             []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: `fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.`},
		},
	}
}
