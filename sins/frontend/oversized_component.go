package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, OversizedComponent{})
}

// OversizedComponent is the sin "oversized-component".
type OversizedComponent struct{}

func (OversizedComponent) Definition() sins.Definition {
	return sins.Definition{
		Name:        "oversized-component",
		Skill:       frontendskill.VueComponents{},
		Description: "A component whose template renders more elements than the project's declared budget — one component doing several jobs",
		Rule:        "Split a component past the project's element budget into single-purpose children; the parent only composes them.",
	}
}
