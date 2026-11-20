package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// CompareSelf states when an enum's comparison method belongs at the call site.
type CompareSelf struct{}

func init() { sins.Register(catalog.PHP, CompareSelf{}) }

func (CompareSelf) Definition() sins.Definition {
	return sins.Definition{
		Name: "compare-self", Skill: skills.EnumsWithBehaviour{},
		Description: "A strict comparison bypasses an enum's verified comparison method.",
		Rule:        "Use the enum's comparison method when the comparison carries no type narrowing.",
		Suggestion:  "Ask the enum to compare itself with the case.",
		Formerly:    []string{"SuggestCompareSelfTrait"},
	}
}
