package frontend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.Frontend, VueComponents{})
}

//go:embed text/vue-components/intro.md
var vueComponentsIntro string

//go:embed text/vue-components/principle.md
var vueComponentsPrinciple string

// VueComponents teaches: extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.
type VueComponents struct{}

func (VueComponents) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "frontend/vue-components",
		Tier:      skill.KeepInMind,
		Order:     15,
		Title:     "Vue components — extract repetition, deep reaches and dispatched views",
		Trigger:   "Extract a component when template markup REPEATS identically, or when an element in a large template reaches DEEP into nested data (data.user.firstName). Repeated markup is one component waiting to be born; a deep reach is a child that knows too much about the data shape and wants the mid-object as a prop. A dispatch (`<SwitchCase>` or a `v-if` chain on one subject) whose cases each render a whole view inline is a component doing a job per case. Read this BEFORE copy-pasting a block of template, reaching `a.b.c` in a sizeable component, or writing a whole view inside a dispatch case.",
		Intro:     vueComponentsIntro,
		Summary:   "extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.",
		Principle: vueComponentsPrinciple,
		Languages: []string{"vue"},
		Related: []skill.Relation{
			{Slug: "frontend/vue-control-flow", Note: "the other half of an honest template — dispatch with `<SwitchCase>`, don't re-test a subject with `v-if` chains."},
		},
	}
}
