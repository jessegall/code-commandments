package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// FlagArgument is the flag-argument sin.
type FlagArgument struct{}

func init() { sins.Register(catalog.Backend, FlagArgument{}) }

// Definition is what the sin states about itself.
func (FlagArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "flag-argument",
		Skill:       skills.BehaviourPerMethod{},
		Description: `a method whose whole body branches on a ` + "`" + `bool` + "`" + ` parameter — or on whether a nullable one was given — two methods sharing one name`,
		Rule:        `Split a method whose body is one branch on a flag into two NAMED methods — never make a call site say ` + "`" + `true` + "`" + `, and never widen a required parameter to ` + "`" + `?T = null` + "`" + ` so that leaving it out means 'all of them'.`,
		Suggestion:  `name each half for what it does (` + "`" + `renderCompact()` + "`" + ` / ` + "`" + `renderFull()` + "`" + `), with any shared middle as a private method both call`,
	}
}
