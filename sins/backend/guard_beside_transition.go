package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// GuardBesideTransition is the guard-beside-transition sin.
type GuardBesideTransition struct{}

func init() { sins.Register(catalog.PHP, GuardBesideTransition{}) }

func (GuardBesideTransition) Definition() sins.Definition {
	return sins.Definition{
		Name:        "guard-beside-transition",
		Skill:       skills.TellDontAsk{},
		Description: "A caller checks an object's state against an enum case and throws, then tells that object to change: the rule sits outside the transition it guards",
		Rule:        "The method that changes an object's state carries the rule for when it may; a caller tells the object and lets it refuse.",
		Suggestion:  "Move the check and the throw into the method the caller runs (`$request->approveSample()` throws `SampleRefused`), and delete the caller's copy.",
	}
}
