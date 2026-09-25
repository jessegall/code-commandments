package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ManufacturedFakeFill is the manufactured-fake-fill sin.
type ManufacturedFakeFill struct{}

func init() { sins.Register(catalog.Backend, ManufacturedFakeFill{}) }

// Definition is what the sin states about itself.
func (ManufacturedFakeFill) Definition() sins.Definition {
	return sins.Definition{
		Name:        "manufactured-fake-fill",
		Skill:       skills.FixAtTheSource{},
		Description: "`?? <empty literal>` filling a required slot (manufactured fake)",
		Rule:        `Fix an absent value at its source; never fill a required slot with a manufactured ` + "`" + `?? ''` + "`" + `/` + "`" + `?? 0` + "`" + `/` + "`" + `?? []` + "`" + `.`,
		Suggestion:  "Throw a named exception at the boundary, or bake a real default into the signature.",
	}
}
