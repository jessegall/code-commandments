package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// HandRolledReplace is `return Order(self.number, self.lines, self.note, "paid")` in a dataclass — every field re-listed to change one.
type HandRolledReplace struct{}

func init() {
	sins.Register(catalog.Python, HandRolledReplace{})
}

// Definition is what the sin states about itself.
func (HandRolledReplace) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-hand-rolled-replace",
		Skill:       skills.ValueObjects{},
		Description: "`return Order(self.number, self.lines, self.note, \"paid\")` in a dataclass — every field re-listed to change one",
		Rule:        "Derive a changed dataclass with `dataclasses.replace`, naming only what changes; don't re-list every field by hand.",
		Suggestion:  "`return replace(self, status=\"paid\")` — a field added later needs no change here.",
	}
}
