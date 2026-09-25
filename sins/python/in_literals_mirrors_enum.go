package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// InLiteralsMirrorsEnum is `x in ("pending", "late")` whose literals are an existing enum's values — a group of its members spelled as raw strings at the call site.
type InLiteralsMirrorsEnum struct{}

func init() {
	sins.Register(catalog.Python, InLiteralsMirrorsEnum{})
}

// Definition is what the sin states about itself.
func (InLiteralsMirrorsEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-in-literals-mirrors-enum",
		Skill:       skills.Enums{},
		Description: "`x in (\"pending\", \"late\")` whose literals are an existing enum's values — a group of its members spelled as raw strings at the call site",
		Rule:        "Test membership in an enum's group through the enum; never re-list its values as literals in an `in` test.",
		Suggestion:  "A property on the enum naming the group — `Status(x).is_open` — or `x in (Status.PENDING, Status.LATE)` when the value is already the enum.",
	}
}
