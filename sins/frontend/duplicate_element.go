package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, DuplicateElement{})
}

// DuplicateElement is the sin "duplicate-element".
type DuplicateElement struct{}

func (DuplicateElement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicate-element",
		Skill:       frontendskill.VueComponents{},
		Description: "Identical markup (3+ elements) repeated 2+ times — within a template or across components — extract one component",
		Rule:        "Extract repeated identical markup into one component.",
	}
}
