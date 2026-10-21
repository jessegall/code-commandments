package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// FrozenDefault is a parameter defaulted to a call that answers differently every time — run once at definition, so every call that omits it gets the first answer.
type FrozenDefault struct{}

func init() {
	sins.Register(catalog.Python, FrozenDefault{})
}

// Definition is what the sin states about itself.
func (FrozenDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-frozen-default",
		Skill:       skills.FixAtTheSource{},
		Description: "a parameter defaulted to `datetime.now()`, `uuid4()` or another call that answers differently each time — it runs once when the def is defined, so every call that omits it gets the moment the module was imported",
		Rule:        "A default is a value, not a computation: anything that must be worked out per call is worked out in the body.",
		Suggestion:  "Default it to `None` and call it inside the body, so each call gets its own answer. It is the container default's twin and takes the same repair.",
	}
}
