package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, DeepNested{})
}

// DeepNested is the sin "deep-nested".
type DeepNested struct{}

func (DeepNested) Definition() sins.Definition {
	return sins.Definition{
		Name:        "deep-nested",
		Skill:       frontendskill.VueComponents{},
		Description: "Template markup nested far too deep — extract a subtree as its own component",
		Rule:        "Extract a far-too-deeply-nested subtree into its own component.",
	}
}
