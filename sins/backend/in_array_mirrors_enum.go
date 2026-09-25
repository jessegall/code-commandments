package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// InArrayMirrorsEnum is the in-array-mirrors-enum sin.
type InArrayMirrorsEnum struct{}

func init() { sins.Register(catalog.Backend, InArrayMirrorsEnum{}) }

// Definition is what the sin states about itself.
func (InArrayMirrorsEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "in-array-mirrors-enum",
		Skill:       skills.EnumsWithBehaviour{},
		Description: "`in_array($x, [literals])` whose literals mirror an existing enum's cases",
		Rule:        `Test membership against the enum (its ` + "`" + `cases()` + "`" + `/` + "`" + `tryFrom` + "`" + `), not an ` + "`" + `in_array` + "`" + ` of literals that mirror its values.`,
		Suggestion:  "Use the enum (`Enum::tryFrom($x)` / a `cases()` check).",
	}
}
