package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// EnumCaseOrChain is the enum-case-or-chain sin.
type EnumCaseOrChain struct{}

func init() { sins.Register(catalog.Backend, EnumCaseOrChain{}) }

// Definition is what the sin states about itself.
func (EnumCaseOrChain) Definition() sins.Definition {
	return sins.Definition{
		Name:        "enum-case-or-chain",
		Skill:       skills.EnumsWithBehaviour{},
		Description: "`$x === Enum::A || $x === Enum::B` — a hand-rolled case-group test",
		Rule:        `Put case-group membership on the enum (a method); don't hand-roll ` + "`" + `$x === Enum::A || $x === Enum::B` + "`" + `.`,
		Suggestion:  "A membership method on the enum (`$x->isFinal()`).",
	}
}
