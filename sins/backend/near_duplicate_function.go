package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NearDuplicateFunction is the near-duplicate-function sin.
type NearDuplicateFunction struct{}

func init() { sins.Register(catalog.Backend, NearDuplicateFunction{}) }

// Definition is what the sin states about itself.
func (NearDuplicateFunction) Definition() sins.Definition {
	return sins.Definition{
		Name:        "near-duplicate-function",
		Skill:       skills.FixAtTheSource{},
		Description: `Redundant methods — two+ functions with the same SHAPE differing only in names/literals (type-2 clone)`,
		Rule:        `Collapse type-2 clones — two functions with the same shape (differing only in names/literals) become one parameterised function.`,
	}
}
