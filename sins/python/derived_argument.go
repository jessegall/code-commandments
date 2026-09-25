package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DerivedArgument is a call that hands over an object and a projection of it — `persist(request, request.channel_id)` — or an object in three pieces, where the function could read them itself.
type DerivedArgument struct{}

func init() {
	sins.Register(catalog.Python, DerivedArgument{})
}

// Definition is what the sin states about itself.
func (DerivedArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-derived-argument",
		Skill:       skills.PassTheObject{},
		Description: "a call that hands over an object and a projection of it — `persist(request, request.channel_id)` — or an object in three pieces, where the function could read them itself",
		Rule:        "Pass the object once and let the function read what it needs from it; if a value can be derived from an argument already passed in, the function should derive it itself.",
		Suggestion:  "Drop the projected parameter and read it inside (`persist(request)` reading `request.channel_id`), or take the object in place of its pieces.",
	}
}
