package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, NearDuplicateElement{})
}

// NearDuplicateElement is the sin "near-duplicate-element".
type NearDuplicateElement struct{}

func (NearDuplicateElement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "near-duplicate-element",
		Skill:       frontendskill.VueComponents{},
		Description: "Markup with one skeleton repeated 2+ times — the same tags, attributes and nesting binding different data — within a template, across components, or as two components' whole templates",
		Rule:        "Extract markup that repeats with different data into one component, and pass what differs as props.",
		Suggestion:  "Make the shared skeleton a component; each place that repeated it renders the component with its own data.",
	}
}
