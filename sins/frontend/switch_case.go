package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, SwitchCase{})
}

// SwitchCase is the sin "switch-case".
type SwitchCase struct{}

func (SwitchCase) Definition() sins.Definition {
	return sins.Definition{
		Name:        "switch-case",
		Skill:       frontendskill.VueControlFlow{},
		Description: "A `v-if`/`v-else-if` chain re-testing the same subject (should be `<SwitchCase :value>`)",
		Rule:        "Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject.",
		Suggestion:  "the `<SwitchCase :value>` component: `commandments scaffold --sin=switch-case`.",
	}
}
