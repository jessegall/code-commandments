package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ComputedBooleanArgument is a method taking only bools that every caller computes from the same object — the decision re-derived at each call site.
type ComputedBooleanArgument struct{}

func init() {
	sins.Register(catalog.Python, ComputedBooleanArgument{})
}

// Definition is what the sin states about itself.
func (ComputedBooleanArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-computed-boolean-argument",
		Skill:       skills.PassTheObject{},
		Description: "a method taking only bools that every caller computes from the same object — the decision re-derived at each call site",
		Rule:        "Hand the method the object that its callers keep asking about, and let the method ask it directly; a bool every caller computes the same way is a decision made in the wrong place.",
		Suggestion:  "Take the object (`text(order)`) and read `order.status`/`order.total` inside, so the rule lives once.",
	}
}
