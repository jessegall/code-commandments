package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ConstClassEnum is the const-class-enum sin.
type ConstClassEnum struct{}

func init() { sins.Register(catalog.Backend, ConstClassEnum{}) }

// Definition is what the sin states about itself.
func (ConstClassEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "const-class-enum",
		Skill:       skills.EnumsWithBehaviour{},
		Description: `A class of 2+ scalar ` + "`" + `const` + "`" + `s and nothing else — a closed set hand-rolled as constants instead of a native enum`,
		Rule:        `Seal a closed set of values as a native backed enum, not a class of scalar ` + "`" + `const` + "`" + `s or loose strings.`,
		Suggestion:  "A native `enum X: string` with the values as cases.",
	}
}
