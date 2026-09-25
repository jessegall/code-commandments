package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// StringMatchMirrorsEnum is the string-match-mirrors-enum sin.
type StringMatchMirrorsEnum struct{}

func init() { sins.Register(catalog.Backend, StringMatchMirrorsEnum{}) }

// Definition is what the sin states about itself.
func (StringMatchMirrorsEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "string-match-mirrors-enum",
		Skill:       skills.EnumsWithBehaviour{},
		Description: "`match`/`switch` over string/int literals that mirror an existing backed enum's case values",
		Rule:        "Dispatch over the enum's cases, not string/int literals that mirror its values.",
		Suggestion:  "Dispatch via a method on the backed enum's cases.",
	}
}
