package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// EnumValueMatch is `match status.value: case "paid": …` at a call site — the enum's raw values matched again where the enum could answer.
type EnumValueMatch struct{}

func init() {
	sins.Register(catalog.Python, EnumValueMatch{})
}

// Definition is what the sin states about itself.
func (EnumValueMatch) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-enum-value-match",
		Skill:       skills.Enums{},
		Description: "`match status.value: case \"paid\": …` at a call site — the enum's raw values matched again where the enum could answer",
		Rule:        "Put a mapping over an enum's cases on the enum, matching its members; never match its raw `.value` at a call site.",
		Suggestion:  "A method on the enum — `def badge(self) -> str: match self: case Status.PAID: …` — and `status.badge()` at the call site.",
	}
}
