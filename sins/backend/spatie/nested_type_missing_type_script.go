package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// NestedTypeMissingTypeScript is the nested-type-missing-typescript sin.
type NestedTypeMissingTypeScript struct{}

func init() { sins.Register(catalog.Backend, NestedTypeMissingTypeScript{}) }

// Definition is what the sin states about itself.
func (NestedTypeMissingTypeScript) Definition() sins.Definition {
	return sins.Definition{
		Name:        "nested-type-missing-typescript",
		Skill:       spatieskills.SpatieData{},
		Description: `A ` + "`" + `#[TypeScript]` + "`" + ` Data has a property typed as a nested ` + "`" + `Data` + "`" + ` class that itself lacks ` + "`" + `#[TypeScript]` + "`" + ` — the transformer emits it as ` + "`" + `undefined` + "`" + `, a silent hole in the generated type (a nested enum is fine; the enum collector auto-generates it)`,
		Rule:        `Every nested ` + "`" + `Data` + "`" + ` reachable on the wire from a ` + "`" + `#[TypeScript]` + "`" + ` class must ALSO be ` + "`" + `#[TypeScript]` + "`" + ` (or the property must declare its shape with ` + "`" + `#[LiteralTypeScriptType]` + "`" + `), or it generates as ` + "`" + `undefined` + "`" + `. Enums need no tag — they auto-generate.`,
		Suggestion:  "Add `#[TypeScript]` to the nested `Data` class the property points at.",
		Requires:    requiresSpatieData,
	}
}
