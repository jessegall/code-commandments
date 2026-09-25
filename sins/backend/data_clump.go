package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DataClump is the data-clump sin.
type DataClump struct{}

func init() { sins.Register(catalog.Backend, DataClump{}) }

// Definition is what the sin states about itself.
func (DataClump) Definition() sins.Definition {
	return sins.Definition{
		Name:        "data-clump",
		Skill:       skills.ValueObjects{},
		Description: "The same 3+ scalar params threaded through 2+ classes (a recurring data clump → one object)",
		Rule:        `Bundle values that always travel together into one object; don't thread 3+ of them as separate params.`,
		Suggestion:  "A value object the params fold into (`Money::of()`, `NodePosition`).",
	}
}
