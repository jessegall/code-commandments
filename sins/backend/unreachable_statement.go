package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UnreachableStatement is the unreachable-statement sin.
type UnreachableStatement struct{}

func init() { sins.Register(catalog.PHP, UnreachableStatement{}) }

func (UnreachableStatement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "unreachable-statement",
		Skill:       skills.GuardClausesAndFlow{},
		Description: `A statement after a return, throw, break or continue in the same block: it can never run.`,
		Rule:        `Every statement in a block can run; code after the block has left is dead.`,
		Suggestion:  `Delete the statement, or move it before the statement that leaves the block if it was meant to run.`,
	}
}
