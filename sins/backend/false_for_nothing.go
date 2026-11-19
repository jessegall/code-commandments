package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// FalseForNothing is the false-for-nothing sin.
type FalseForNothing struct{}

func init() { sins.Register(catalog.PHP, FalseForNothing{}) }

func (FalseForNothing) Definition() sins.Definition {
	return sins.Definition{
		Name:        "false-for-nothing",
		Skill:       skills.Absence{},
		Description: `A return typed ` + "`" + `X|false` + "`" + `: ` + "`" + `false` + "`" + ` stands in for "nothing" or "failed", and every caller must test for it.`,
		Rule:        `Never return ` + "`" + `false` + "`" + ` to say nothing was found or something failed — throw a named exception, return an empty value, or return an Option.`,
		Suggestion:  `Throw a named exception for a failure, or return an empty collection or ` + "`" + `Option` + "`" + ` for a genuine absence.`,
	}
}
