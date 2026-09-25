package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MessageStringRaise is `raise Exception/RuntimeError("…")` — a failure that names nothing, described in prose at the raise site.
type MessageStringRaise struct{}

func init() {
	sins.Register(catalog.Python, MessageStringRaise{})
}

// Definition is what the sin states about itself.
func (MessageStringRaise) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-message-string-raise",
		Skill:       skills.Exceptions{},
		Description: "`raise Exception/RuntimeError(\"…\")` — a failure that names nothing, described in prose at the raise site",
		Rule:        "Raise a named exception built by a classmethod factory, never a bare `Exception` or `RuntimeError` with a message written at the raise.",
		Suggestion:  "Give the failure a class of its own with a classmethod that takes the values and writes the message once — `raise NoActiveRequest.for_(name)` — so a caller can catch it by name.",
	}
}
