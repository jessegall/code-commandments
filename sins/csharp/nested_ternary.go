package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// NestedTernary is a `?:` with another `?:` as one of its branches — several decisions packed into one expression.
type NestedTernary struct{}

func init() {
	sins.Register(catalog.CSharp, NestedTernary{})
}

// Definition is what the sin states about itself.
func (NestedTernary) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-nested-ternary",
		Skill:       skills.Flow{},
		Description: "a `?:` with another `?:` as one of its branches — several decisions packed into one expression",
		Rule:        "Use one `?:` for one choice; for more, use a `switch` expression or early returns.",
		Suggestion:  "Rewrite it as a `switch` expression (`grams switch { < 100 => \"small\", < 1000 => \"medium\", _ => \"large\" }`) or as `if` statements that return.",
	}
}
