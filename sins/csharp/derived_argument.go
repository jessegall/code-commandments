package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// DerivedArgument is a call that hands over an object and a projection of it — `Persist(request, request.ChannelId)` — or an object in three pieces, where the method could read them itself.
type DerivedArgument struct{}

func init() {
	sins.Register(catalog.CSharp, DerivedArgument{})
}

// Definition is what the sin states about itself.
func (DerivedArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-derived-argument",
		Skill:       skills.PassTheObject{},
		Description: "a call that hands over an object and a projection of it — `Persist(request, request.ChannelId)` — or an object in three pieces, where the method could read them itself",
		Rule:        "Pass the object once and let the method read what it needs from it; a value the method can derive from an argument it already gets is one it should derive itself.",
		Suggestion:  "Drop the projected parameter and read it inside (`Persist(request)` reading `request.ChannelId`), or take the object in place of its pieces.",
	}
}
