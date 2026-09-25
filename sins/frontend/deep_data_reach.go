package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, DeepDataReach{})
}

// DeepDataReach is the sin "deep-data-reach".
type DeepDataReach struct{}

func (DeepDataReach) Definition() sins.Definition {
	return sins.Definition{
		Name:        "deep-data-reach",
		Skill:       frontendskill.VueComponents{},
		Description: "A group of elements in a sizeable template that all reach deep into the same nested object (≥2 distinct fields) — extract the shared mid-object into a component that takes it as a prop.",
		Rule:        "Pass the mid-object as a prop; don't reach deep into nested data from the template.",
	}
}
