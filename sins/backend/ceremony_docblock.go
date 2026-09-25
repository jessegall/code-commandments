package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// CeremonyDocblock is the ceremony-docblock sin.
type CeremonyDocblock struct{}

func init() { sins.Register(catalog.Backend, CeremonyDocblock{}) }

// Definition is what the sin states about itself.
func (CeremonyDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "ceremony-docblock",
		Skill:       skills.Documentation{},
		Description: "Docblock that only restates the typed signature (`@param Type $x`, no description)",
		Rule:        `A docblock must add meaning beyond the signature — drop ` + "`" + `@param Type $x` + "`" + ` lines that only restate an already-typed parameter.`,
	}
}
