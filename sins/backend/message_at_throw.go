package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MessageAtThrow is the message-at-throw sin.
type MessageAtThrow struct{}

func init() { sins.Register(catalog.Backend, MessageAtThrow{}) }

// Definition is what the sin states about itself.
func (MessageAtThrow) Definition() sins.Definition {
	return sins.Definition{
		Name:        "message-at-throw",
		Skill:       skills.Exceptions{},
		Description: "Message string built at the throw site (no domain values / named factory)",
		Rule:        "Pass domain VALUES to a named factory; never assemble the message string at the throw site.",
		Suggestion:  "A static `::for($values)` factory on the exception that builds the sentence.",
	}
}
