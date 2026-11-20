package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

// Arguments teaches how a signature shows what a function needs and returns.
type Arguments struct{}

func init() {
	skill.Register(catalog.Shared, Arguments{})
}

func (Arguments) Definition() skill.Definition {
	languages := make([]string, 0, len(contract.Languages))
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}
	return skill.Definition{
		Slug: "shared/arguments", Tier: skill.Mandatory, Order: 7,
		Title:     "Arguments: name the input and return the result",
		Trigger:   "Read before adding a parameter, passing pieces of a value, or writing a result through an argument.",
		Intro:     "A signature shows what the function needs and what the caller gets back.",
		Summary:   "Pass the value the function needs, and return what it produces.",
		Principle: "Resolve a lookup at the caller when the callee only needs the result. Give distinct operations distinct names.",
		Languages: languages,
	}
}
