package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// RestatedComment is a comment above a statement whose every word the statement already spells — `// set the total to the order total` over `var total = order.Total;`.
type RestatedComment struct{}

func init() {
	sins.Register(catalog.CSharp, RestatedComment{})
}

// Definition is what the sin states about itself.
func (RestatedComment) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-restated-comment",
		Skill:       skills.Documentation{},
		Description: "a comment above a statement whose every word the statement already spells — `// set the total to the order total` over `var total = order.Total;`",
		Rule:        "A comment must say something the code does not; if every word of it is already in the code below, delete it.",
		Suggestion:  "Delete the comment. If the statement is unclear, name it better (extract a well-named method or variable); keep a comment only for a reason the code cannot state.",
	}
}
