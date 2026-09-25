package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// AllNullableData is the all-nullable-data sin.
type AllNullableData struct{}

func init() { sins.Register(catalog.Backend, AllNullableData{}) }

// Definition is what the sin states about itself.
func (AllNullableData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "all-nullable-data",
		Skill:       spatieskills.SpatieData{},
		Description: "All-nullable \"god\" DTO — every field `?T`/defaulted (type doesn't tell the truth)",
		Rule:        `A DTO's field types must tell the truth — make required fields non-nullable; don't default every field to ` + "`" + `?T` + "`" + `/null. If every field genuinely IS optional and same-shaped (a callback bag, a filter set, a money breakdown), make each non-nullable with a Null Object / identity default on the value type instead. "But the fields ARE genuinely optional" is the trigger for this fix, NOT an exemption from it: genuine absence is exactly what ` + "`" + `T|Optional = new Optional()` + "`" + ` models — for ANY ` + "`" + `Data` + "`" + ` (it is unrelated to ` + "`" + `#[TypeScript]` + "`" + `) — so "no identity value fits" / "a wire null is honest" means reach for ` + "`" + `Optional` + "`" + `, never leave every field ` + "`" + `?T = null` + "`" + `.`,
		Suggestion:  `Retype each field to the truth: required → non-nullable, no default; genuinely-absent → ` + "`" + `T|Optional = new Optional()` + "`" + ` (dropped from output, not ` + "`" + `null` + "`" + `); always-present-but-emptyable → a Null Object / identity default (` + "`" + `Grid $grid = new Grid()` + "`" + `, ` + "`" + `Status $s = Status::Default` + "`" + `). If a whole SUB-object may be absent, put the optional on the CONTAINER field (` + "`" + `Type|Optional $x = new Optional()` + "`" + `) and keep that type's leaves concrete — don't scatter ` + "`" + `?T` + "`" + `/` + "`" + `Optional` + "`" + ` across every leaf.`,
		Requires:    requiresSpatieData,
	}
}
