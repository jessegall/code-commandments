package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// SubjectLadder is an `if`/`elif` chain of four or more rungs that each test the same subject for equality with a constant — a dispatch written as a ladder.
type SubjectLadder struct{}

func init() {
	sins.Register(catalog.Python, SubjectLadder{})
}

// Definition is what the sin states about itself.
func (SubjectLadder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-subject-ladder",
		Skill:       skills.Flow{},
		Description: "An `if`/`elif` chain of four or more rungs that each test the same subject for equality with a constant — a dispatch written as a ladder.",
		Rule:        "Dispatch on a value with an `Enum` that answers per case, a dict keyed by the value, or a `match` — never a ladder of `==` tests on one subject.",
		Suggestion:  "Make the closed set an `Enum` and put the per-case answer on it, or look the answer up in a dict keyed by the value; a `match` fits a structural dispatch.",
	}
}
