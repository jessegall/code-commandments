package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// FlagArgument is a function whose whole body branches on a `bool` parameter — or on whether an optional one was given — two functions sharing one name.
type FlagArgument struct{}

func init() {
	sins.Register(catalog.Python, FlagArgument{})
}

// Definition is what the sin states about itself.
func (FlagArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-flag-argument",
		Skill:       skills.BehaviourPerMethod{},
		Description: "a function whose whole body branches on a `bool` parameter — or on whether an optional one was given — two functions sharing one name",
		Rule:        "Split a function whose body is one branch on a flag into two named functions — never make a call say `True`, and never widen a required parameter to `X | None = None` so that leaving it out means 'all of them'.",
		Suggestion:  "Name each half for what it does (`render_compact()` / `render_full()`), with any shared middle as a private function both call.",
	}
}
