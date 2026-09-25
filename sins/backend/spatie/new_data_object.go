package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// NewDataObject is the new-data-object sin.
type NewDataObject struct{}

func init() { sins.Register(catalog.Backend, NewDataObject{}) }

// Definition is what the sin states about itself.
func (NewDataObject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "new-data-object",
		Skill:       spatieskills.SpatieData{},
		Description: "`new <Data subclass>` instead of `::from()` / a `fromX()` factory",
		Rule:        "Build a rich `Data` object via `::from()`/a `fromX()` factory, never `new`.",
		Suggestion:  "`X::from(...)` (or a `fromY()` factory).",
		Requires:    requiresSpatieData,
	}
}
