package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed behaviour_per_method.intro.md
	behaviourPerMethodIntro string
	//go:embed behaviour_per_method.principle.md
	behaviourPerMethodPrinciple string
)

// BehaviourPerMethod teaches: a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call say which it wants, instead of passing a bare `true`.
type BehaviourPerMethod struct{}

func init() {
	skill.Register(catalog.CSharp, BehaviourPerMethod{})
}

// Definition is what the skill states about itself.
func (BehaviourPerMethod) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/behaviour-per-method",
		Tier:      skill.KeepInMind,
		Order:     39,
		Title:     "C# behaviour per method — one method, one job",
		Trigger:   "A C# parameter that picks which behaviour runs instead of feeding one. When a method's whole body is `if (flag) { … } else { … }`, it is two methods sharing a name, and every call reads `Render(order, true)`. Read this before adding a `bool` parameter, before making a required parameter nullable so that leaving it out means 'all of them', and when a call passes a bare `true` or `false`.",
		Intro:     behaviourPerMethodIntro,
		Summary:   "a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call say which it wants, instead of passing a bare `true`.",
		Principle: behaviourPerMethodPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/behaviour-per-method", Note: "the same discipline in PHP."},
			{Slug: "csharp/enums", Note: "when the choice has more than two cases, it is an `enum` — and the behaviour belongs beside it."},
			{Slug: "csharp/flow", Note: "an early return that guards is the shape this one is NOT."},
		},
	}
}
