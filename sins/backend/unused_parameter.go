package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UnusedParameter is the unused-parameter sin.
type UnusedParameter struct{}

func init() { sins.Register(catalog.PHP, UnusedParameter{}) }

func (UnusedParameter) Definition() sins.Definition {
	return sins.Definition{
		Name:        "unused-parameter",
		Skill:       skills.TypeHonesty{},
		Description: `A parameter of a method no subclass can override that its body never reads: every caller must pass a value nothing uses.`,
		Rule:        `A signature asks only for what its body uses; a parameter no override can need and nothing reads is dead.`,
		Suggestion:  `Remove the parameter and the argument at each call site, or use it where it was meant to be used.`,
	}
}
