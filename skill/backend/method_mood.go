package backend

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

// MethodMood teaches: commands are imperatives (`hide()`), state predicates are questions (`isHidden()`).
type MethodMood struct{}

func init() {
	skill.Register(catalog.Backend, MethodMood{})
}

// Definition is what the skill states about itself.
func (MethodMood) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/method-mood",
		Tier:      skill.Mandatory,
		Order:     8,
		Title:     "Method mood — an order, or a question",
		Trigger:   "What GRAMMAR a method name is written in: a command is an imperative (`hide()`, `openFor()`, `write()`), never a third-person narration (`hides()`, `opensFor()`, `writes()`); a method that answers `bool` about its own state wears a question (`isShown()`, `hasParent()`, `awaitsAnswer()`). Read this when you name or rename a method, and when a naming sin points here.",
		Intro:     methodMoodIntro,
		Summary:   "commands are imperatives (`hide()`), state predicates are questions (`isHidden()`).",
		Principle: methodMoodPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/role-vocabulary", Note: "what a class is called and what that name promises; this is the same contract one scale down."},
			{Slug: "backend/tell-dont-ask", Note: `an order is the point: if you find yourself narrating what an object does, you are probably reaching into it.`},
		},
	}
}
