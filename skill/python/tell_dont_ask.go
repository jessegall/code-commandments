package python

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

// TellDontAsk teaches: behaviour belongs with its data: move a loop over one object's collection onto that object, and replace an `isinstance` ladder with a method each type answers.
type TellDontAsk struct{}

func init() {
	skill.Register(catalog.Python, TellDontAsk{})
}

// Definition is what the skill states about itself.
func (TellDontAsk) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/tell-dont-ask",
		Tier:      skill.KeepInMind,
		Order:     41,
		Title:     "Python tell, don't ask — behaviour lives with its data",
		Trigger:   "Behaviour belongs with the data it works on. Read this BEFORE you write a Python function that loops over another object's collection or walks its parts to work out something that object could answer, and before an `if isinstance(x, A): … elif isinstance(x, B): …` ladder that asks what a value IS to decide what to do with it — the answer is a method on the type (`order.total()`, `shape.area()`).",
		Intro:     tellDontAskIntro,
		Summary:   "behaviour belongs with its data: move a loop over one object's collection onto that object, and replace an `isinstance` ladder with a method each type answers.",
		Principle: tellDontAskPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/tell-dont-ask", Note: "the same discipline over PHP."},
			{Slug: "python/enums", Note: "a closed set of cases carries its per-case behaviour on the `Enum`."},
			{Slug: "python/value-objects", Note: "the type the behaviour moves onto."},
		},
	}
}
