package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ConvertedArgument is the converted-argument sin.
type ConvertedArgument struct{}

func init() { sins.Register(catalog.Backend, ConvertedArgument{}) }

// Definition is what the sin states about itself.
func (ConvertedArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "converted-argument",
		Skill:       skills.PassTheObject{},
		Description: `A parameter typed as the already-converted form instead of the raw value, so every call site repeats the same conversion before calling it (e.g. ` + "`" + `Raises::of(ClassAlias::of($interaction), …)` + "`" + `).`,
		Rule:        `Declare the parameter in the form callers already have, and do the conversion inside the callee — so the conversion rule lives in one place.`,
		Suggestion:  `Move the wrapper into the callee and widen the parameter to the type being wrapped; every call site then passes the value it means, and a site that forgets the conversion stops compiling.`,
	}
}
