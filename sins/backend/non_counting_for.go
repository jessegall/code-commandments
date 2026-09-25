package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// NonCountingFor is the non-counting-for sin.
type NonCountingFor struct{}

func init() { sins.Register(catalog.Backend, NonCountingFor{}) }

// Definition is what the sin states about itself.
func (NonCountingFor) Definition() sins.Definition {
	return sins.Definition{
		Name:        "non-counting-for",
		Skill:       skills.GuardClausesAndFlow{},
		Description: `A ` + "`" + `for` + "`" + ` loop that looks like it's counting, but its step actually assigns the next item instead of incrementing a counter.`,
		Rule:        `Keep ` + "`" + `for` + "`" + ` for a counted loop, whose step advances a counter; walk with a ` + "`" + `while` + "`" + `, or let the type hand out its own sequence.`,
		Suggestion:  `A ` + "`" + `while` + "`" + ` over an explicit cursor — or better, an iterator on the type being walked, so the caller never holds the cursor at all.`,
	}
}
