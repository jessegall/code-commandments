package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, PropDrilling{})
}

// PropDrilling is the sin "prop-drilling".
type PropDrilling struct{}

func (PropDrilling) Definition() sins.Definition {
	return sins.Definition{
		Name:        "prop-drilling",
		Skill:       frontendskill.VueComponents{},
		Description: "A prop forwarded through a chain of 2+ components, none of which read it — passed down through components that only pass it further.",
		Rule:        "Don't thread a prop through a component that doesn't use it; provide/inject it, or give the child the data directly.",
	}
}
