package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MutableStaticState is the mutable-static-state sin.
type MutableStaticState struct{}

func init() { sins.Register(catalog.Backend, MutableStaticState{}) }

// Definition is what the sin states about itself.
func (MutableStaticState) Definition() sins.Definition {
	return sins.Definition{
		Name:        "mutable-static-state",
		Skill:       skills.FixAtTheSource{},
		Description: `A write to a static property — really a global variable with a namespace attached — where whichever write happens last wins, so the order code runs in changes the result.`,
		Rule:        "Hold changing state on an INSTANCE someone owns and passes; never write a static property.",
		Suggestion:  `Constructor-inject the state as a collaborator, so who holds it (and who may change it) is written down.`,
	}
}
