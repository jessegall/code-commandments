package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed repeated_call_helper.intro.md
	repeatedCallHelperIntro string
	//go:embed repeated_call_helper.principle.md
	repeatedCallHelperPrinciple string
)

// RepeatedCallHelper teaches: a call, a guard or a type check written the same way at 2+ sites belongs as one named member on the type it is about.
type RepeatedCallHelper struct{}

func init() {
	skill.Register(catalog.CSharp, RepeatedCallHelper{})
}

// Definition is what the skill states about itself.
func (RepeatedCallHelper) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/repeated-call-helper",
		Tier:      skill.Mandatory,
		Order:     40,
		Title:     "C# repeated call helper — name what you keep writing",
		Trigger:   "When you write the same thing the same way at site after site in C# — the same `with { Status = … }` copy, the same call with the same named argument, the same compound `if` condition, the same `x is A a && a.Inner is B` type check. Read this before copying a condition or a call from one method into another, and when a repeated-guard or repeated-call finding points here.",
		Intro:     repeatedCallHelperIntro,
		Summary:   "a call, a guard or a type check written the same way at 2+ sites belongs as one named member on the type it is about.",
		Principle: repeatedCallHelperPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/repeated-call-helper", Note: "the same discipline in PHP."},
			{Slug: "csharp/duplication", Note: "a whole method body written twice, rather than one call or condition."},
			{Slug: "csharp/fix-at-the-source", Note: "name the question where the data lives, not beside each caller."},
		},
	}
}
