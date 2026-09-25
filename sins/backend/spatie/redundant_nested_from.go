package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// RedundantNestedFrom is the redundant-nested-from sin.
type RedundantNestedFrom struct{}

func init() { sins.Register(catalog.Backend, RedundantNestedFrom{}) }

// Definition is what the sin states about itself.
func (RedundantNestedFrom) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-nested-from",
		Skill:       spatieskills.SpatieDataHydration{},
		Description: "A nested `X::from([...])` fills a slot the parent `::from` already auto-hydrates from the array",
		Rule:        `Pass the plain array for a nested ` + "`" + `Data` + "`" + ` / ` + "`" + `#[DataCollectionOf]` + "`" + ` slot — don't wrap it in ` + "`" + `X::from([...])` + "`" + `.`,
		Suggestion:  "`'slot' => ['a' => 1]` (or `[['a' => 1], ...]` for a collection), not `X::from(['a' => 1])`.",
		Requires:    requiresSpatieData,
	}
}
