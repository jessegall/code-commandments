package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// HandKeyRemap is the hand-key-remap sin.
type HandKeyRemap struct{}

func init() { sins.Register(catalog.Backend, HandKeyRemap{}) }

// Definition is what the sin states about itself.
func (HandKeyRemap) Definition() sins.Definition {
	return sins.Definition{
		Name:        "hand-key-remap",
		Skill:       spatieskills.SpatieDataHydration{},
		Description: `A ` + "`" + `::from([...])` + "`" + ` mechanically renames ` + "`" + `$src['snake_key']` + "`" + ` → ` + "`" + `camelKey` + "`" + ` by hand, instead of a class-level ` + "`" + `#[MapInputName]` + "`" + ``,
		Rule:        `Map a snake_case boundary with one class-level ` + "`" + `#[MapInputName(SnakeCaseMapper::class)]` + "`" + ` + ` + "`" + `::from($src)` + "`" + `, not a hand-written key translation.`,
		Suggestion:  "`#[MapInputName(SnakeCaseMapper::class)]` on the class, then `SomeData::from($src)`.",
		Requires:    requiresSpatieData,
	}
}
