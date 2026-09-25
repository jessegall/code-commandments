package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// ManualOutputTransform is the manual-output-transform sin.
type ManualOutputTransform struct{}

func init() { sins.Register(catalog.Backend, ManualOutputTransform{}) }

// Definition is what the sin states about itself.
func (ManualOutputTransform) Definition() sins.Definition {
	return sins.Definition{
		Name:        "manual-output-transform",
		Skill:       spatieskills.PageObjects{},
		Description: `A ` + "`" + `Data` + "`" + ` computed slot hand-flattens a value object into a wire array, instead of a ` + "`" + `#[WithTransformer]` + "`" + ` that owns the serialized shape`,
		Rule:        `Shape a property's wire output with a ` + "`" + `#[WithTransformer]` + "`" + ` (+ a matching ` + "`" + `#[TypeScriptType]` + "`" + `), never a computed getter that hand-builds the reshaped array.`,
		Suggestion:  `Keep the real value-object type and add ` + "`" + `#[WithTransformer(SomeTransformer::class)]` + "`" + ` — plus ` + "`" + `#[TypeScriptType(...)]` + "`" + ` so the generated TypeScript matches the transformed shape.`,
		Requires:    requiresSpatieData,
	}
}
