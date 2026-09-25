package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// BloatedDocblock is a class docstring of two or more paragraphs of prose — an essay that says the class does too much.
type BloatedDocblock struct{}

func init() {
	sins.Register(catalog.Python, BloatedDocblock{})
}

// Definition is what the sin states about itself.
func (BloatedDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-bloated-docblock",
		Skill:       skills.Documentation{},
		Description: "a class docstring of two or more paragraphs of prose — an essay that says the class does too much",
		Rule:        "Keep a class docstring to one tight paragraph; sections for attributes and examples are fine, an essay is not.",
		Suggestion:  "Cut the docstring to what the class is; if it takes an essay, split the class.",
	}
}
