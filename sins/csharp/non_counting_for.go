package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// NonCountingFor is a `for` loop whose step assigns the next item instead of moving a counter — a walk written as a count.
type NonCountingFor struct{}

func init() {
	sins.Register(catalog.CSharp, NonCountingFor{})
}

// Definition is what the sin states about itself.
func (NonCountingFor) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-non-counting-for",
		Skill:       skills.Flow{},
		Description: "a `for` loop whose step assigns the next item instead of moving a counter — a walk written as a count",
		Rule:        "Use `for` only to count; walk with a `while` loop, or let the type hand out its items as an `IEnumerable<T>`.",
		Suggestion:  "Rewrite it as `while (link != null) { …; link = link.Next; }`, or give the type an iterator (`yield return`) and loop over it with `foreach`.",
	}
}
