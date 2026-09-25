package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// BloatedDocblock is a type whose doc comment runs to two or more paragraphs — usually a sign the type does too much.
type BloatedDocblock struct{}

func init() {
	sins.Register(catalog.CSharp, BloatedDocblock{})
}

// Definition is what the sin states about itself.
func (BloatedDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-bloated-docblock",
		Skill:       skills.Documentation{},
		Description: "a type whose doc comment runs to two or more paragraphs — usually a sign the type does too much",
		Rule:        "Keep a type's doc comment to one short paragraph; if it needs more, the type is doing too much.",
		Suggestion:  "Cut the comment to one sentence about what the type is, and split the type if the rest describes a second job.",
	}
}
