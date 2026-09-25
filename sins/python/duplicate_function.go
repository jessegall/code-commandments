package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DuplicateFunction is copy-pasted code — two+ Python functions or methods with an identical body, formatting, comments and docstrings aside.
type DuplicateFunction struct{}

func init() {
	sins.Register(catalog.Python, DuplicateFunction{})
}

// Definition is what the sin states about itself.
func (DuplicateFunction) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicate-python-function",
		Skill:       skills.Duplication{},
		Description: "Copy-pasted code — two+ Python functions or methods with an identical body, formatting, comments and docstrings aside",
		Rule:        "Hoist a function body written twice into one shared function, and call it from both places.",
		Suggestion:  "Move the body to one function in a module both callers import (or a method on the class that owns the data), and replace every copy with a call to it.",
	}
}
