package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ArchaeologyComment is a comment or docstring narrating the code's history — where it lived, what it replaced, what it no longer is.
type ArchaeologyComment struct{}

func init() {
	sins.Register(catalog.Python, ArchaeologyComment{})
}

// Definition is what the sin states about itself.
func (ArchaeologyComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-archaeology-comment",
		Skill:       skills.Documentation{},
		Description: "a comment or docstring narrating the code's history — where it lived, what it replaced, what it no longer is",
		Rule:        "Say what the code is now; the history lives in git, not in a comment or a docstring.",
		Suggestion:  "Delete the history. If a reason still matters, state it in the present tense.",
	}
}
