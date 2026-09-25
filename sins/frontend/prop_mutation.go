package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, PropMutation{})
}

// PropMutation is the sin "prop-mutation".
type PropMutation struct{}

func (PropMutation) Definition() sins.Definition {
	return sins.Definition{
		Name:        "prop-mutation",
		Skill:       frontendskill.VueComponents{},
		Description: "A prop is written to — `v-model` bound to it, or `@event=\"prop = …\"` — but props are read-only (a build error or a silent no-op).",
		Rule:        "Never write a prop. For two-way state use `defineModel`; otherwise emit an `update:` event and let the parent own the value.",
	}
}
