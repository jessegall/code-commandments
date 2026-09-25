package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// TernaryStatement is the ternary-statement sin.
type TernaryStatement struct{}

func init() { sins.Register(catalog.Backend, TernaryStatement{}) }

// Definition is what the sin states about itself.
func (TernaryStatement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "ternary-statement",
		Skill:       skills.GuardClausesAndFlow{},
		Description: `a bare ` + "`" + `$cond ? doThis() : doThat();` + "`" + ` statement — a ternary whose value nothing reads, so it is choosing an ACTION, not a value`,
		Rule:        `Choose an action with ` + "`" + `if` + "`" + `/` + "`" + `else` + "`" + `; a ternary chooses a VALUE, so never write one whose result nothing reads.`,
	}
}
