package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// EnumCaseOrChain is `s == Status.PENDING or s == Status.LATE` — a group of an enum's members re-derived at the call site instead of named on the enum.
type EnumCaseOrChain struct{}

func init() {
	sins.Register(catalog.Python, EnumCaseOrChain{})
}

// Definition is what the sin states about itself.
func (EnumCaseOrChain) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-enum-case-or-chain",
		Skill:       skills.Enums{},
		Description: "`s == Status.PENDING or s == Status.LATE` — a group of an enum's members re-derived at the call site instead of named on the enum",
		Rule:        "Name a group of an enum's members as a method or property on the enum; don't re-list the members in an `or` chain at every call site.",
		Suggestion:  "A property on the enum — `def is_open(self) -> bool: return self in (Status.PENDING, Status.LATE)` — and `s.is_open` at the call site.",
	}
}
