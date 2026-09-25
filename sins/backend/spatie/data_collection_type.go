package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// DataCollectionType is the data-collection-type sin.
type DataCollectionType struct{}

func init() { sins.Register(catalog.Backend, DataCollectionType{}) }

// Definition is what the sin states about itself.
func (DataCollectionType) Definition() sins.Definition {
	return sins.Definition{
		Name:        "data-collection-type",
		Skill:       spatieskills.SpatieData{},
		Description: `A ` + "`" + `Data` + "`" + ` property is TYPED as ` + "`" + `DataCollection` + "`" + ` — it should be ` + "`" + `array` + "`" + ` (or ` + "`" + `Collection` + "`" + `) with ` + "`" + `#[DataCollectionOf(X)]` + "`" + `; the ` + "`" + `DataCollection` + "`" + ` type emits malformed TypeScript and skips element-typed hydration`,
		Rule:        `Never type a ` + "`" + `Data` + "`" + ` property as ` + "`" + `DataCollection` + "`" + `. Type it ` + "`" + `array` + "`" + ` (preferred) or ` + "`" + `Collection` + "`" + ` and add ` + "`" + `#[DataCollectionOf(X::class)]` + "`" + ` — element typing drives hydration, nested validation, and clean TypeScript.`,
		Suggestion:  `` + "`" + `#[DataCollectionOf(NodeData::class)] public readonly array $nodes;` + "`" + `, not ` + "`" + `public readonly DataCollection $nodes;` + "`" + `.`,
		Requires:    requiresSpatieData,
	}
}
