package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DivergentTwin is the divergent-twin sin.
type DivergentTwin struct{}

func init() { sins.Register(catalog.Backend, DivergentTwin{}) }

// Definition is what the sin states about itself.
func (DivergentTwin) Definition() sins.Definition {
	return sins.Definition{
		Name:        "divergent-twin",
		Skill:       skills.FixAtTheSource{},
		Description: `Two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other.`,
		Rule:        "Put shared behaviour in one place, so a step that must always happen can't be forgotten in a copy.",
		Suggestion:  "Make the shorter function call the longer one, or have both call one shared function.",
	}
}
