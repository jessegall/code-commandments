package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Vue, RetestedSwitchCase{})
}

// RetestedSwitchCase is the sin "re-tested-switch-case".
type RetestedSwitchCase struct{}

func (RetestedSwitchCase) Definition() sins.Definition {
	return sins.Definition{
		Name:        "re-tested-switch-case",
		Skill:       frontendskill.VueControlFlow{},
		Description: "A `<SwitchCase>` case that tests again the value the `<SwitchCase>` already dispatched on",
		Rule:        "A `<SwitchCase>` slot already knows which case it is; a `v-if` on the same value inside it is a second, drifting copy of the dispatch.",
		Suggestion:  "Give each value its own slot (`#paid`, `#refunded`) and drop the inner test.",
	}
}
