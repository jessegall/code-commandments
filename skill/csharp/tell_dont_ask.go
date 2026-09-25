package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed tell_dont_ask.intro.md
	tellDontAskIntro string
	//go:embed tell_dont_ask.principle.md
	tellDontAskPrinciple string
)

// TellDontAsk teaches: behaviour belongs with its data: move a loop over one object's collection onto that object, and replace a type `switch` with a member each type answers.
type TellDontAsk struct{}

func init() {
	skill.Register(catalog.CSharp, TellDontAsk{})
}

// Definition is what the skill states about itself.
func (TellDontAsk) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/tell-dont-ask",
		Tier:      skill.KeepInMind,
		Order:     42,
		Title:     "C# tell, don't ask — behaviour lives with its data",
		Trigger:   "Behaviour belongs with the data it works on. Read this BEFORE you write a C# method that loops over another object's collection or walks its parts to work out something that object could answer, and before a `switch` with type patterns (`case Circle c:`) or an `if (x is A a) … else if (x is B b) …` ladder that asks what a value IS to decide what to do with it — the answer is a member of the type (`order.Total()`, `shape.Area()`).",
		Intro:     tellDontAskIntro,
		Summary:   "behaviour belongs with its data: move a loop over one object's collection onto that object, and replace a type `switch` with a member each type answers.",
		Principle: tellDontAskPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/tell-dont-ask", Note: "the same discipline in PHP."},
			{Slug: "csharp/enums", Note: "a closed set of values carries its per-case knowledge beside the `enum`."},
			{Slug: "csharp/value-objects", Note: "the type the behaviour moves onto."},
		},
	}
}
