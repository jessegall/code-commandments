package typescript

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	typescriptskill "github.com/jessegall/code-commandments/skill/typescript"
)

func init() {
	sins.Register(catalog.TypeScript, FalselyOptionalField{})
}

// FalselyOptionalField is the sin "falsely-optional-field".
type FalselyOptionalField struct{}

func (FalselyOptionalField) Definition() sins.Definition {
	return sins.Definition{
		Name:        "falsely-optional-field",
		Skill:       typescriptskill.Absence{},
		Description: "A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen",
		Rule:        "Do not declare a field optional when it always has a value: drop the `?` and the `| null`, and the defences downstream go with them.",
		Suggestion:  "Declare it as its plain type — the initialiser already proves it is total.",
	}
}
