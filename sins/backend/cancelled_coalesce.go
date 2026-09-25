package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// CancelledCoalesce is the cancelled-coalesce sin.
type CancelledCoalesce struct{}

func init() { sins.Register(catalog.Backend, CancelledCoalesce{}) }

// Definition is what the sin states about itself.
func (CancelledCoalesce) Definition() sins.Definition {
	return sins.Definition{
		Name:        "cancelled-coalesce",
		Skill:       skills.Absence{},
		Description: "`??` cancelled by the comparison it sits in — `($x ?? '') !== ''`",
		Rule:        `Ask about absence directly (` + "`" + `$x !== null` + "`" + `); never coalesce to a value only to compare against that same value.`,
		Suggestion:  `Say both halves out loud — ` + "`" + `$x !== null && $x !== ''` + "`" + ` — or make the value non-nullable at its source so only one question is left.`,
	}
}
