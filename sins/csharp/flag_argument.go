package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// FlagArgument is a method whose whole body branches on a `bool` parameter — `if (compact) … else …` — two methods sharing one name.
type FlagArgument struct{}

func init() {
	sins.Register(catalog.CSharp, FlagArgument{})
}

// Definition is what the sin states about itself.
func (FlagArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-flag-argument",
		Skill:       skills.BehaviourPerMethod{},
		Description: "a method whose whole body branches on a `bool` parameter — `if (compact) … else …` — two methods sharing one name",
		Rule:        "Split a method a parameter chooses between into two methods, each named for what it does.",
		Suggestion:  "`Render(order, bool compact)` becomes `RenderCompact(order)` and `RenderFull(order)`, with anything they share in a private method both call.",
	}
}
