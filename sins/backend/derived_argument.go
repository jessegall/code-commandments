package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DerivedArgument is the derived-argument sin.
type DerivedArgument struct{}

func init() { sins.Register(catalog.Backend, DerivedArgument{}) }

// Definition is what the sin states about itself.
func (DerivedArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "derived-argument",
		Skill:       skills.PassTheObject{},
		Description: `Passing the same object twice — once whole and once broken into a piece (` + "`" + `persist($request, $request->shopId())` + "`" + `), or broken into several pieces at once (` + "`" + `new AgentTurn($r->output(), $r->failed(), $r->errorOutput())` + "`" + `) — when the callee could derive each piece itself from the one object.`,
		Rule:        `Pass the object itself, not values derived from it — if a callee needs several pieces off one object, give it the object once and let it work out the rest.`,
		Suggestion:  `Give the parameter the subject's type and move the derivations inside the callee; the call site then says what it means instead of spelling out the pieces.`,
	}
}
