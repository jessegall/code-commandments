package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// EnumValueMatch is the enum-value-match sin.
type EnumValueMatch struct{}

func init() { sins.Register(catalog.Backend, EnumValueMatch{}) }

// Definition is what the sin states about itself.
func (EnumValueMatch) Definition() sins.Definition {
	return sins.Definition{
		Name:        "enum-value-match",
		Skill:       skills.EnumsWithBehaviour{},
		Description: `A ` + "`" + `match` + "`" + `/` + "`" + `switch` + "`" + ` over an enum's ` + "`" + `->value` + "`" + ` at the call site — logic that belongs on the enum but lives elsewhere instead.`,
		Rule:        "Put per-case behaviour on the enum; never `match`/`switch` over its `->value` at a call site.",
		Suggestion:  "A method on the backed enum (`$x->label()`, `$x->isPaid()`).",
	}
}
