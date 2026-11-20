package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// TransformerWithoutTsType is the transformer-without-ts-type sin.
type TransformerWithoutTsType struct{}

func init() { sins.Register(catalog.PHP, TransformerWithoutTsType{}) }

func (TransformerWithoutTsType) Definition() sins.Definition {
	return sins.Definition{
		Name:        "transformer-without-ts-type",
		Skill:       spatieskills.PageObjects{},
		Description: "On a Data class exported with `#[TypeScript]`, a custom `#[WithTransformer]` changes the wire shape and the generated type still describes the PHP value.",
		Rule:        "On Data classes exported to TypeScript, pair a custom `#[WithTransformer]` with `#[TypeScriptType]` or `#[LiteralTypeScriptType]` that declares the wire shape.",
		Suggestion:  `Add ` + "`" + `#[TypeScriptType('...')]` + "`" + ` (or ` + "`" + `#[LiteralTypeScriptType(...)]` + "`" + `) stating the type the transformer serializes to, so the generated frontend type matches the wire.`,
		Requires:    requiresSpatieData,
	}
}
