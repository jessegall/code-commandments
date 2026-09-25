package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DivergentTwin is two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other.
type DivergentTwin struct{}

func init() {
	sins.Register(catalog.Python, DivergentTwin{})
}

// Definition is what the sin states about itself.
func (DivergentTwin) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-divergent-twin",
		Skill:       skills.FixAtTheSource{},
		Description: "two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other.",
		Rule:        "Put shared behaviour in one place, so a step that must always happen can't be forgotten in a copy.",
		Suggestion:  "Make the shorter function call the longer one, or have both call one shared function.",
	}
}
