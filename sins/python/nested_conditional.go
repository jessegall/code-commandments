package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NestedConditional is `a if x else b if y else c` — a conditional expression inside another's branch, a branching decision folded into one line.
type NestedConditional struct{}

func init() {
	sins.Register(catalog.Python, NestedConditional{})
}

// Definition is what the sin states about itself.
func (NestedConditional) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-nested-conditional",
		Skill:       skills.Flow{},
		Description: "`a if x else b if y else c` — a conditional expression inside another's branch, a branching decision folded into one line",
		Rule:        "Unfold a conditional expression nested in another's branch into a `match`, a lookup or guard clauses; don't chain `… if … else … if … else …`.",
		Suggestion:  "A `match` over the subject, a dict lookup for a table of values, or a small function whose guards return early.",
	}
}
