package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NearDuplicateFunction is a near-copy — two+ Python functions or methods with one control-flow skeleton that differ only in their local names or the literals they use (a path, a key, a message).
type NearDuplicateFunction struct{}

func init() {
	sins.Register(catalog.Python, NearDuplicateFunction{})
}

// Definition is what the sin states about itself.
func (NearDuplicateFunction) Definition() sins.Definition {
	return sins.Definition{
		Name:        "near-duplicate-python-function",
		Skill:       skills.Duplication{},
		Description: "A near-copy — two+ Python functions or methods with one control-flow skeleton that differ only in their local names or the literals they use (a path, a key, a message)",
		Rule:        "Merge two functions that differ only in a literal into one, and pass what differs as a parameter.",
		Suggestion:  "Name the literal that differs, make it a parameter of one shared function, and call that from both places.",
	}
}
