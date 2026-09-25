package python

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

// BehaviourPerMethod teaches: a parameter that picks WHICH behaviour runs means two functions share one name — split them and let the call say which it wants, instead of passing a bare `True`.
type BehaviourPerMethod struct{}

func init() {
	skill.Register(catalog.Python, BehaviourPerMethod{})
}

// Definition is what the skill states about itself.
func (BehaviourPerMethod) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/behaviour-per-method",
		Tier:      skill.KeepInMind,
		Order:     38,
		Title:     "Python behaviour per method — never a flag that picks",
		Trigger:   "A Python parameter that selects WHICH behaviour runs rather than feeding one. When a function's whole body is `if flag: … else: …`, it is two functions sharing a name, and every call reads `render(order, True)`. Read this before adding a `bool` parameter, before widening a required parameter to `X | None = None` so that leaving it out means 'all of them', and when a call passes a bare `True`/`False`.",
		Intro:     behaviourPerMethodIntro,
		Summary:   "a parameter that picks WHICH behaviour runs means two functions share one name — split them and let the call say which it wants, instead of passing a bare `True`.",
		Principle: behaviourPerMethodPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/behaviour-per-method", Note: "the same discipline over PHP methods."},
			{Slug: "python/enums", Note: "when the choice has more than two cases, it is an `Enum` — and the behaviour belongs on it."},
			{Slug: "python/flow", Note: "an early return that guards is the shape this one is NOT."},
		},
	}
}
