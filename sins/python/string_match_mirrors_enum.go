package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// StringMatchMirrorsEnum is `match raw: case "pending": …` whose cases are an existing enum's values — dispatching on loose strings the enum already seals.
type StringMatchMirrorsEnum struct{}

func init() {
	sins.Register(catalog.Python, StringMatchMirrorsEnum{})
}

// Definition is what the sin states about itself.
func (StringMatchMirrorsEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-string-match-mirrors-enum",
		Skill:       skills.Enums{},
		Description: "`match raw: case \"pending\": …` whose cases are an existing enum's values — dispatching on loose strings the enum already seals",
		Rule:        "Dispatch on the enum, not on loose strings that mirror its values; turn the string into the enum where it arrives.",
		Suggestion:  "`Status(raw)` at the boundary, then `match status: case Status.PENDING: …` — or a method on the enum.",
	}
}
