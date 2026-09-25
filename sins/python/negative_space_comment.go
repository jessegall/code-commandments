package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NegativeSpaceComment is a comment or docstring defending the code against a misunderstanding nobody actually had — what it is not, rather than what it is.
type NegativeSpaceComment struct{}

func init() {
	sins.Register(catalog.Python, NegativeSpaceComment{})
}

// Definition is what the sin states about itself.
func (NegativeSpaceComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-negative-space-comment",
		Skill:       skills.Documentation{},
		Description: "a comment or docstring defending the code against a misunderstanding nobody actually had — what it is not, rather than what it is.",
		Rule:        "State what the code is; a comment defending it against an objection nobody raised means the code should make itself plain.",
		Suggestion:  "Delete the defence. If the code needs it, make the code say what it is.",
	}
}
