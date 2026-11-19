package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ReassignedParameter is the reassigned-parameter sin.
type ReassignedParameter struct{}

func init() { sins.Register(catalog.PHP, ReassignedParameter{}) }

func (ReassignedParameter) Definition() sins.Definition {
	return sins.Definition{
		Name:        "reassigned-parameter",
		Skill:       skills.TypeHonesty{},
		Description: `A parameter overwritten inside its method, so one name holds the value the caller passed and later a different one.`,
		Rule:        `A parameter keeps the value its caller passed; a new value gets a name of its own.`,
		Suggestion:  `Assign the new value to a local named for what it is ($trimmed = trim($name)), or ask the caller for the finished value.`,
	}
}
