package backend

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

// BehaviourPerMethod teaches: a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call site say which it wants, instead of passing a bare `true`.
type BehaviourPerMethod struct{}

func init() {
	skill.Register(catalog.Backend, BehaviourPerMethod{})
}

// Definition is what the skill states about itself.
func (BehaviourPerMethod) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/behaviour-per-method",
		Tier:      skill.KeepInMind,
		Order:     18,
		Title:     "One method, one behaviour — never a flag that picks",
		Trigger:   "A parameter that selects WHICH behaviour runs rather than feeding one. When a method's whole body is `if ($flag) { … } else { … }`, it is two methods sharing a name, and every call site reads `render($order, true)` — a truth value that says nothing about what it asked for. Split it into two named methods and let the caller say which it wants. Read this before adding a `bool` parameter, before widening a required parameter to `?T = null` so that leaving it out means 'all of them', before writing a method whose body is one branch on a parameter, and when a call site passes a bare `true`/`false` literal.",
		Intro:     behaviourPerMethodIntro,
		Summary:   "a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call site say which it wants, instead of passing a bare `true`.",
		Principle: behaviourPerMethodPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/enums-with-behaviour", Note: "when the choice has more than two cases, it is an enum — and the behaviour belongs on it."},
			{Slug: "backend/guard-clauses-and-flow", Note: "an early return that guards is the shape this one is NOT; check preconditions at the top and leave."},
			{Slug: "backend/pass-the-object", Note: `the sibling on the other side: a caller that pre-decides with a bool it computed from an object it holds.`},
		},
	}
}
