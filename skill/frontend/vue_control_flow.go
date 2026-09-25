package frontend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.Frontend, VueControlFlow{})
}

//go:embed text/vue-control-flow/intro.md
var vueControlFlowIntro string

//go:embed text/vue-control-flow/principle.md
var vueControlFlowPrinciple string

// VueControlFlow teaches: dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.
type VueControlFlow struct{}

func (VueControlFlow) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "frontend/vue-control-flow",
		Tier:      skill.KeepInMind,
		Order:     16,
		Title:     "Vue control flow — dispatch on a value, don't chain conditionals",
		Trigger:   "Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision wearing many conditionals. Read this BEFORE writing a v-if/v-else-if chain in a Vue template.",
		Intro:     vueControlFlowIntro,
		Summary:   "dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.",
		Principle: vueControlFlowPrinciple,
		Languages: []string{"vue"},
		Related: []skill.Relation{
			{Slug: "frontend/vue-components", Note: "a `<SwitchCase>` IS a component — the same extract-don't-inline instinct."},
		},
	}
}
