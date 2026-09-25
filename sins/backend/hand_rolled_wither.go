package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// HandRolledWither is the hand-rolled-wither sin.
type HandRolledWither struct{}

func init() { sins.Register(catalog.Backend, HandRolledWither{}) }

// Definition is what the sin states about itself.
func (HandRolledWither) Definition() sins.Definition {
	return sins.Definition{
		Name:        "hand-rolled-wither",
		Skill:       skills.ValueObjects{},
		Description: `A wither method rebuilds the whole object by re-listing every constructor field, so adding a new field means updating every wither in the class.`,
		Rule:        `A wither should only say what changes: ` + "`" + `clone($this, ['x' => $x])` + "`" + ` states the intent, while re-listing every field just repeats the constructor in every wither.`,
		Suggestion:  `Replace ` + "`" + `new self($this->a, $this->b, $changed)` + "`" + ` with ` + "`" + `clone($this, ['c' => $changed])` + "`" + ` — ` + "`" + `repent` + "`" + ` does it for you.`,
	}
}
