package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DuplicateFunction is the duplicate-function sin.
type DuplicateFunction struct{}

func init() { sins.Register(catalog.Backend, DuplicateFunction{}) }

// Definition is what the sin states about itself.
func (DuplicateFunction) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicate-function",
		Skill:       skills.FixAtTheSource{},
		Description: "Copy-pasted code — two+ functions with an identical AST (formatting/comments aside)",
		Rule:        "Extract copy-pasted code — two functions with an identical AST must become one.",
	}
}
