package backend

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

// TellDontAsk teaches: behaviour belongs with its data (feature envy): don't exile a loop over one object's collection into a separate class — move it onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). A Strategy over flat scalar fields is the exception.
type TellDontAsk struct{}

func init() {
	skill.Register(catalog.Backend, TellDontAsk{})
}

// Definition is what the skill states about itself.
func (TellDontAsk) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/tell-dont-ask",
		Tier:      skill.KeepInMind,
		Order:     11,
		Title:     "Tell, don't ask — behaviour belongs with its data",
		Trigger:   "Behaviour belongs with the data it operates on (feature envy, Fowler). If a method reaches through ONE other object's internal structure — looping its collection, walking its tree of parts — to work out something the object should answer itself, that logic is exiled from its home; Move the Method onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). Read this BEFORE you write a `*Detector`/`*Walker`/`*Finder` that iterates one object's collection from the outside. NOTE the exception: a policy/Strategy over the object's flat scalar fields (a grade, a label, a classification) is NOT envy.",
		Intro:     tellDontAskIntro,
		Summary:   "behaviour belongs with its data (feature envy): don't exile a loop over one object's collection into a separate class — move it onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). A Strategy over flat scalar fields is the exception.",
		Principle: tellDontAskPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/value-objects", Note: "behaviour belongs on the typed object that owns the data."},
			{Slug: "backend/fix-at-the-source", Note: "move the method to where the data lives, not a downstream class that walks it."},
		},
	}
}
