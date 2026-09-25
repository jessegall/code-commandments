package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// PhantomNullable is the phantom-nullable sin.
type PhantomNullable struct{}

func init() { sins.Register(catalog.Backend, PhantomNullable{}) }

// Definition is what the sin states about itself.
func (PhantomNullable) Definition() sins.Definition {
	return sins.Definition{
		Name:        "phantom-nullable",
		Skill:       skills.TypeHonesty{},
		Description: `Phantom nullable — a field typed ` + "`" + `?T` + "`" + ` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens`,
		Rule:        `If a nullable field is assumed present everywhere its value flows and guarded nowhere, the null is a lie — make it non-nullable and let it be required, failing hard at construction on a real miss.`,
	}
}
