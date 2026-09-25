package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ComputedBooleanArgument is the computed-boolean-argument sin.
type ComputedBooleanArgument struct{}

func init() { sins.Register(catalog.Backend, ComputedBooleanArgument{}) }

// Definition is what the sin states about itself.
func (ComputedBooleanArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "computed-boolean-argument",
		Skill:       skills.PassTheObject{},
		Description: `A parameter that's just true/false, computed by every caller from the same object it could be given instead.`,
		Rule:        "Take the SUBJECT and ask it — never a bool every caller derives from that same object.",
		Suggestion:  "swap the flags for the object the callers already hold: `CornerInset::for($editor)`",
	}
}
