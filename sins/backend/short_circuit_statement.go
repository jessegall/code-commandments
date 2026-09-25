package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ShortCircuitStatement is the short-circuit-statement sin.
type ShortCircuitStatement struct{}

func init() { sins.Register(catalog.Backend, ShortCircuitStatement{}) }

// Definition is what the sin states about itself.
func (ShortCircuitStatement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "short-circuit-statement",
		Skill:       skills.GuardClausesAndFlow{},
		Description: `a bare ` + "`" + `$a && $b->do();` + "`" + ` statement — a short-circuit whose result nothing reads, so the operator is an ` + "`" + `if` + "`" + ` in disguise`,
		Rule:        `Branch with an ` + "`" + `if` + "`" + `; never run work off the right side of a bare ` + "`" + `&&` + "`" + `/` + "`" + `||` + "`" + ` statement whose result nothing reads.`,
	}
}
