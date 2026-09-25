package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// AllOptionalData is the all-optional-data sin.
type AllOptionalData struct{}

func init() { sins.Register(catalog.Backend, AllOptionalData{}) }

// Definition is what the sin states about itself.
func (AllOptionalData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "all-optional-data",
		Skill:       spatieskills.SpatieData{},
		Description: `Every field of a ` + "`" + `Data` + "`" + ` object is ` + "`" + `T|Optional` + "`" + ` — the type promises nothing is ever present; the absence belongs on the CONTAINER field where it's used`,
		Rule:        `Don't make every field of a Data object ` + "`" + `Optional` + "`" + ` (the all-nullable smell in another skin). Almost always it's the WHOLE object that is present-or-absent — mark the enclosing field ` + "`" + `Type|Optional` + "`" + ` at its use site and give this object honest, concrete leaves, so if it exists it's a valid whole.`,
		Suggestion:  `Give each leaf a concrete default (` + "`" + `int $columns = 1` + "`" + `) and move the optionality up to where this type is used: ` + "`" + `public readonly Grid|Optional $grid = new Optional();` + "`" + `.`,
		Requires:    requiresSpatieData,
	}
}
