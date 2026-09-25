package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// BloatedDocblock is the bloated-docblock sin.
type BloatedDocblock struct{}

func init() { sins.Register(catalog.Backend, BloatedDocblock{}) }

// Definition is what the sin states about itself.
func (BloatedDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "bloated-docblock",
		Skill:       skills.Documentation{},
		Description: "Multi-paragraph class docblock (class too big)",
		Rule:        `Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.`,
	}
}
