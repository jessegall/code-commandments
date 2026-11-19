package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// DuplicatedBranchTail is the duplicated-branch-tail sin.
type DuplicatedBranchTail struct{}

func init() { sins.Register(catalog.PHP, DuplicatedBranchTail{}) }

func (DuplicatedBranchTail) Definition() sins.Definition {
	return sins.Definition{
		Name:        "duplicated-branch-tail",
		Skill:       skills.GuardClausesAndFlow{},
		Description: `Every branch of an ` + "`" + `if` + "`" + `/` + "`" + `else` + "`" + ` ends with the same statement: it runs whichever way the condition goes.`,
		Rule:        `A statement every branch ends with belongs after the conditional, written once.`,
		Suggestion:  `Move the shared last statement below the ` + "`" + `if` + "`" + `/` + "`" + `else` + "`" + `.`,
	}
}
