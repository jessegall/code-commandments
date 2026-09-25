package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RestatedComment is a `#` comment that only narrates the statement below it — every word of it already spelled by the code.
type RestatedComment struct{}

func init() {
	sins.Register(catalog.Python, RestatedComment{})
}

// Definition is what the sin states about itself.
func (RestatedComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-restated-comment",
		Skill:       skills.Documentation{},
		Description: "a `#` comment that only narrates the statement below it — every word of it already spelled by the code",
		Rule:        "A comment must say something the code does not; one whose every word is in the line below is noise.",
		Suggestion:  "Delete the comment, or replace it with the reason the code cannot state.",
	}
}
