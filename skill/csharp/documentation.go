package csharp

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

// Documentation teaches: short, present-tense doc comments; rare comments; never narrate the past.
type Documentation struct{}

func init() {
	skill.Register(catalog.CSharp, Documentation{})
}

// Definition is what the skill states about itself.
func (Documentation) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/documentation",
		Tier:      skill.Mandatory,
		Order:     43,
		Title:     "C# documentation — short, present tense, rare",
		Trigger:   "How to document C#, and mostly not to. A `/// <summary>` is a line or two about the code as it is NOW; a `//` comment is rare and only explains a non-obvious *why*; never narrate the past or a change (\"previously…\", \"used to…\", \"refactored to…\"). Read this the moment you are about to write a `///` doc comment, a `<param>`/`<returns>` tag, or a `//` comment.",
		Intro:     documentationIntro,
		Summary:   "short, present-tense doc comments; rare comments; never narrate the past.",
		Principle: documentationPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/documentation", Note: "the same discipline for PHP docblocks."},
			{Slug: "csharp/fix-at-the-source", Note: "fix the shape instead of documenting the workaround."},
		},
	}
}
