package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// DataToArrayRoundtrip is the data-to-array-roundtrip sin.
type DataToArrayRoundtrip struct{}

func init() { sins.Register(catalog.Backend, DataToArrayRoundtrip{}) }

// Definition is what the sin states about itself.
func (DataToArrayRoundtrip) Definition() sins.Definition {
	return sins.Definition{
		Name:        "data-to-array-roundtrip",
		Skill:       spatieskills.SpatieDataHydration{},
		Description: `A ` + "`" + `X::from(...)->toArray()` + "`" + ` sits in a ` + "`" + `::from` + "`" + ` slot typed ` + "`" + `X` + "`" + ` that re-hydrates it — build → array → build`,
		Rule:        `Don't ` + "`" + `->toArray()` + "`" + ` a ` + "`" + `Data` + "`" + ` into a slot that re-hydrates it; pass the object (or the source array) directly.`,
		Suggestion:  "Drop the `->toArray()` — the nested-`Data` / `#[DataCollectionOf]` slot takes the object as-is.",
		Requires:    requiresSpatieData,
	}
}
