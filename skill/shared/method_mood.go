package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed method_mood.md
var methodMoodBody string

// MethodMood teaches naming a method by what a call to it is, in every language: an order in the imperative, a yes-or-no about the object as a question.
type MethodMood struct{}

func init() {
	skill.Register(catalog.Shared, MethodMood{})
}

func (MethodMood) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/method-mood",
		Tier:      skill.Mandatory,
		Order:     8,
		Title:     "Method mood — a command is an order, a predicate is a question",
		Trigger:   "Naming a method: one that acts (returns nothing or itself) or one that answers yes or no about its own object. Read this BEFORE you name a command in the third person (`hides()`, `entersTestMode()`) or a boolean as a bare verb (`spins()`, `binds()`), and when a method-mood finding points here.",
		Summary:   "name a command in the imperative (`hide()`), and a yes-or-no about the object itself as a question (`isShown()`, `hasParent()`, `canRetry()`); a predicate that takes what it compares against (`contains(item)`) stays third person.",
		Body:      methodMoodBody,
		Formerly:  []string{"php/method-mood", "csharp/method-mood", "python/method-mood"},
		Languages: languages,
	}
}
