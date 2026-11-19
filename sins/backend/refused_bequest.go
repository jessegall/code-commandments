package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// RefusedBequest is the refused-bequest sin.
type RefusedBequest struct{}

func init() { sins.Register(catalog.PHP, RefusedBequest{}) }

func (RefusedBequest) Definition() sins.Definition {
	return sins.Definition{
		Name:        "refused-bequest",
		Skill:       skills.TypeHonesty{},
		Description: `A method that overrides or implements an inherited one only to throw: the class claims a contract it refuses to keep.`,
		Rule:        `A subtype keeps every promise its parent makes; a method that only throws to refuse its inheritance says the class is not that type.`,
		Suggestion:  `Split the contract so the class implements only what it can do, or compose the parent instead of extending it.`,
	}
}
