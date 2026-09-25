package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// SubjectLadder is an `if`/`else if` chain of four or more rungs that each compare the same subject with a constant — a dispatch written as a ladder.
type SubjectLadder struct{}

func init() {
	sins.Register(catalog.CSharp, SubjectLadder{})
}

// Definition is what the sin states about itself.
func (SubjectLadder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-subject-ladder",
		Skill:       skills.Flow{},
		Description: "An `if`/`else if` chain of four or more rungs that each compare the same subject with a constant — a dispatch written as a ladder.",
		Rule:        "Dispatch on a value with a `switch` expression, or put the per-case behaviour on the type — never a ladder of `==` tests on one subject.",
		Suggestion:  "Replace the ladder with a `switch` expression over the subject (the compiler checks it covers every case), or move each case's behaviour onto the type it belongs to.",
	}
}
