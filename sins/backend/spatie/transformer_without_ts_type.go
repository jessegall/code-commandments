package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// TransformerWithoutTsType is the transformer-without-ts-type sin.
type TransformerWithoutTsType struct{}

func init() { sins.Register(catalog.Backend, TransformerWithoutTsType{}) }

// Definition is what the sin states about itself.
func (TransformerWithoutTsType) Definition() sins.Definition {
	return sins.Definition{
		Name:        "transformer-without-ts-type",
		Skill:       spatieskills.PageObjects{},
		Description: `A ` + "`" + `#[WithTransformer]` + "`" + ` changes a property's wire shape but has no paired ` + "`" + `#[TypeScriptType]` + "`" + `/` + "`" + `#[LiteralTypeScriptType]` + "`" + `, so the generated TypeScript keeps the wrong (PHP) type`,
		Rule:        `Pair every custom ` + "`" + `#[WithTransformer]` + "`" + ` with a ` + "`" + `#[TypeScriptType]` + "`" + ` / ` + "`" + `#[LiteralTypeScriptType]` + "`" + ` that declares the transformed wire shape.`,
		Suggestion:  `Add ` + "`" + `#[TypeScriptType('...')]` + "`" + ` (or ` + "`" + `#[LiteralTypeScriptType(...)]` + "`" + `) stating the type the transformer serializes to, so the generated frontend type matches the wire.`,
		Requires:    requiresSpatieData,
	}
}
