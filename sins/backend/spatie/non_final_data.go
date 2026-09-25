package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// NonFinalData is the non-final-data sin.
type NonFinalData struct{}

func init() { sins.Register(catalog.Backend, NonFinalData{}) }

// Definition is what the sin states about itself.
func (NonFinalData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "non-final-data",
		Skill:       spatieskills.SpatieData{},
		Description: "Data class not `final` / props not `readonly` promoted",
		Rule:        "Seal a Data class `final` with `readonly` promoted props — it's a leaf, not a base.",
		Requires:    requiresSpatieData,
	}
}
