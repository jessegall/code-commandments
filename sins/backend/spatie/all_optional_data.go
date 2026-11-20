package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// AllOptionalData is the all-optional-data sin.
type AllOptionalData struct{}

func init() { sins.Register(catalog.PHP, AllOptionalData{}) }

func (AllOptionalData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "all-optional-data",
		Skill:       spatieskills.SpatieData{},
		Description: `Every field of a ` + "`" + `Data` + "`" + ` object is ` + "`" + `T|Optional` + "`" + ` — the type promises nothing is ever present; the absence belongs on the CONTAINER field where it's used`,
		Rule:        `Give a Data object the fields it needs to be valid. When the whole object can be absent, put ` + "`" + `Optional` + "`" + ` on the enclosing field. A partial update can make every field optional when its consumer applies each supplied field independently and preserves the current value for each omitted field.`,
		Suggestion:  `Give each leaf a concrete default (` + "`" + `int $columns = 1` + "`" + `) and move the optionality up to where this type is used: ` + "`" + `public readonly Grid|Optional $grid = new Optional();` + "`" + `.`,
		Requires:    requiresSpatieData,
	}
}
