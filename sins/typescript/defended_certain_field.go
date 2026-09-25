package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	typescriptskill "github.com/jessegall/code-commandments/skill/typescript"
)

func init() {
	sins.Register(catalog.TypeScript, DefendedCertainField{})
}

// DefendedCertainField is the sin "defended-certain-field".
type DefendedCertainField struct{}

func (DefendedCertainField) Definition() sins.Definition {
	return sins.Definition{
		Name:        "defended-certain-field",
		Skill:       typescriptskill.Absence{},
		Description: "An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, so the code doubts something the design already rules out.",
		Rule:        "Reach for `?.` only where the type admits absence; on a field declared total, it is noise that makes the next reader wonder if it can be missing.",
		Suggestion:  "A plain `.` — the declaration already guarantees it.",
	}
}
