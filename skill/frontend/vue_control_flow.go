package frontend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

func init() {
	skill.Register(catalog.Vue, VueControlFlow{})
}

//go:embed vue_control_flow.md
var vueControlFlowBody string

// VueControlFlow teaches: dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.
type VueControlFlow struct{}

func (VueControlFlow) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "vue/vue-control-flow",
		Tier:      skill.KeepInMind,
		Order:     16,
		Title:     "Vue control flow — dispatch on a value, don't chain conditionals",
		Trigger:   "Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision wearing many conditionals. Read this BEFORE writing a v-if/v-else-if chain in a Vue template.",
		Summary:   "dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.",
		Body:      vueControlFlowBody,
		Languages: []string{"vue"},
		Related: []skill.Relation{
			{Slug: "vue/vue-components", Note: "a `<SwitchCase>` IS a component — the same extract-don't-inline instinct."},
		},
	}
}
