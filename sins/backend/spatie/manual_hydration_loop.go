package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// ManualHydrationLoop is the manual-hydration-loop sin.
type ManualHydrationLoop struct{}

func init() { sins.Register(catalog.Backend, ManualHydrationLoop{}) }

// Definition is what the sin states about itself.
func (ManualHydrationLoop) Definition() sins.Definition {
	return sins.Definition{
		Name:        "manual-hydration-loop",
		Skill:       spatieskills.SpatieData{},
		Description: "Collections hydrated with `::from()` per item instead of `#[DataCollectionOf]` + `::collect()`",
		Rule:        "Hydrate a collection with `#[DataCollectionOf]` + `::collect()`, not a per-item `::from()` loop.",
		Suggestion:  "`#[DataCollectionOf(X::class)]` + `X::collect($rows)`.",
		Requires:    requiresSpatieData,
	}
}
