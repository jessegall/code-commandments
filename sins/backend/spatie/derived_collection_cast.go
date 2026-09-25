package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// DerivedCollectionCast is the derived-collection-cast sin.
type DerivedCollectionCast struct{}

func init() { sins.Register(catalog.Backend, DerivedCollectionCast{}) }

// Definition is what the sin states about itself.
func (DerivedCollectionCast) Definition() sins.Definition {
	return sins.Definition{
		Name:        "derived-collection-cast",
		Skill:       spatieskills.SpatieDataHydration{},
		Description: `A ` + "`" + `#[DataCollectionOf]` + "`" + ` is filled by mapping a factory over inputs at the call site, where a ` + "`" + `#[WithCast]` + "`" + ` should own the derivation`,
		Rule:        `Move an element derivation (` + "`" + `array_map(E::for(...), $xs)` + "`" + `) into a ` + "`" + `#[WithCast]` + "`" + ` / ` + "`" + `IterableItemCast` + "`" + ` on the collection property; pass the raw list.`,
		Suggestion:  `` + "`" + `#[WithCast(SomeCast::class)] public array $items` + "`" + ` — the cast runs ` + "`" + `E::for(...)` + "`" + ` per item; the call site passes the raw values.`,
		Requires:    requiresSpatieData,
	}
}
