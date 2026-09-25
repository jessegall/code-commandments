package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RepeatedNamedCall is the same `**changes` call is built the same way with the same keyword at 2+ sites — an operation that has no name on the type it belongs to.
type RepeatedNamedCall struct{}

func init() {
	sins.Register(catalog.Python, RepeatedNamedCall{})
}

// Definition is what the sin states about itself.
func (RepeatedNamedCall) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-repeated-named-call",
		Skill:       skills.RepeatedCallHelper{},
		Description: "the same `**changes` call is built the same way with the same keyword at 2+ sites — an operation that has no name on the type it belongs to.",
		Rule:        "Name a keyword call you keep writing the same way: a method on the type — `node.with_meta(payload)` — that hides the call and the construction.",
		Suggestion:  "Add a method to the receiver's class that makes the call, and call that at every site.",
	}
}
