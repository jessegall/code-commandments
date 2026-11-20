package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// AllNullableData is the all-nullable-data sin.
type AllNullableData struct{}

func init() { sins.Register(catalog.PHP, AllNullableData{}) }

func (AllNullableData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "all-nullable-data",
		Skill:       spatieskills.SpatieData{},
		Description: "All-nullable \"god\" DTO — every field `?T`/defaulted (type doesn't tell the truth)",
		Rule:        "Use non-nullable types for values the class guarantees. Use `T|Optional = new Optional()` for omitted fields in any Data class. A single required nullable value can carry an explicit state, such as clearing a selection. Give empty filters, collections, or money breakdowns a typed identity where one exists.",
		Suggestion:  `Retype each field to the truth: required → non-nullable, no default; genuinely-absent → ` + "`" + `T|Optional = new Optional()` + "`" + ` (dropped from output, not ` + "`" + `null` + "`" + `); always-present-but-emptyable → a Null Object / identity default (` + "`" + `Grid $grid = new Grid()` + "`" + `, ` + "`" + `Status $s = Status::Default` + "`" + `). If a whole SUB-object may be absent, put the optional on the CONTAINER field (` + "`" + `Type|Optional $x = new Optional()` + "`" + `) and keep that type's leaves concrete — don't scatter ` + "`" + `?T` + "`" + `/` + "`" + `Optional` + "`" + ` across every leaf.`,
		Requires:    requiresSpatieData,
	}
}
