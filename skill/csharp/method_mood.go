package csharp

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

// MethodMood teaches: commands are orders (`Hide()`), state checks are questions (`IsHidden`, `HasParent`).
type MethodMood struct{}

func init() {
	skill.Register(catalog.CSharp, MethodMood{})
}

// Definition is what the skill states about itself.
func (MethodMood) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/method-mood",
		Tier:      skill.Mandatory,
		Order:     38,
		Title:     "C# method mood — an order, or a question",
		Trigger:   "Naming a C# method or property — especially one that returns `bool`, or a method that changes the object and returns `void`. Read this BEFORE you name something `Hides`, `Binds` or `Spins`, and when a method-mood finding points here.",
		Intro:     methodMoodIntro,
		Summary:   "commands are orders (`Hide()`), state checks are questions (`IsHidden`, `HasParent`).",
		Principle: methodMoodPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/method-mood", Note: "the same discipline in PHP."},
			{Slug: "csharp/class-layout", Note: "where a type keeps its state, next to what its members are called."},
		},
	}
}
