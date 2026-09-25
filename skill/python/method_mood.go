package python

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed method_mood.intro.md
	methodMoodIntro string
	//go:embed method_mood.principle.md
	methodMoodPrinciple string
)

// MethodMood teaches: commands are imperatives (`hide()`), state predicates are questions (`is_hidden()`).
type MethodMood struct{}

func init() {
	skill.Register(catalog.Python, MethodMood{})
}

// Definition is what the skill states about itself.
func (MethodMood) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/method-mood",
		Tier:      skill.Mandatory,
		Order:     37,
		Title:     "Python method mood — an order, or a question",
		Trigger:   "Naming a Python method or a `@property` — especially one that returns a `bool`, or one that changes the object and returns nothing. Read this BEFORE you name a method `hides`, `binds` or `spins`, and when a method-mood finding points here.",
		Intro:     methodMoodIntro,
		Summary:   "commands are imperatives (`hide()`), state predicates are questions (`is_hidden()`).",
		Principle: methodMoodPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/method-mood", Note: "the same discipline over PHP methods."},
			{Slug: "python/class-layout", Note: "where a class keeps its state, beside what its methods are called."},
		},
	}
}
