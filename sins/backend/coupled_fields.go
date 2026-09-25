package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// CoupledFields is the coupled-fields sin.
type CoupledFields struct{}

func init() { sins.Register(catalog.Backend, CoupledFields{}) }

// Definition is what the sin states about itself.
func (CoupledFields) Definition() sins.Definition {
	return sins.Definition{
		Name:        "coupled-fields",
		Skill:       skills.ValueObjects{},
		Description: `A class's own fields always change and get checked together — one concept split across several fields — and should be folded into a single value object.`,
		Rule:        `Fields that always change together are really one type — extract them into a value object and use it directly; don't keep a field that just duplicates a nested object's property.`,
		Suggestion:  `Fold the co-moving fields into one value object (name the existing type when the clump already is one); drop a field that duplicates a nested object's property.`,
	}
}
