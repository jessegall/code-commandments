package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MiddleMan is the middle-man sin.
type MiddleMan struct{}

func init() { sins.Register(catalog.PHP, MiddleMan{}) }

func (MiddleMan) Definition() sins.Definition {
	return sins.Definition{
		Name:        "middle-man",
		Skill:       skills.TellDontAsk{},
		Description: `A class whose every public method only hands its arguments, unchanged, to the same field: it adds nothing between the caller and the object that does the work.`,
		Rule:        `A class earns its place by doing something; one that only forwards every call to one collaborator is a middle man — let callers talk to that collaborator.`,
		Suggestion:  `Inject the collaborator where the middle man is used and delete the class, or move real behaviour into it.`,
	}
}
