package csharp

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

// Templates teaches: a multi-line string is a raw string literal that SHOWS its output, never a list of line fragments joined.
type Templates struct{}

func init() {
	skill.Register(catalog.CSharp, Templates{})
}

// Definition is what the skill states about itself.
func (Templates) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/templates",
		Tier:      skill.KeepInMind,
		Order:     41,
		Title:     "C# templates — a multi-line string shows its output",
		Trigger:   "Writing a multi-line string a C# program produces — generated code, a config block, a report, an email body. Read this the moment you find yourself joining a list of lines with `string.Join(\"\\n\", …)` or calling `AppendLine` line after line, so the OUTPUT stays readable in the source that produces it.",
		Intro:     templatesIntro,
		Summary:   "a multi-line string is a raw string literal that SHOWS its output, never a list of line fragments joined.",
		Principle: templatesPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/templates", Note: "the same discipline with PHP heredocs."},
		},
	}
}
