package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RepeatedGuard is the same compound `and` condition recurs in 2+ places — it still counts even when reordered, or read through a local variable — and nobody has named it.
type RepeatedGuard struct{}

func init() {
	sins.Register(catalog.Python, RepeatedGuard{})
}

// Definition is what the sin states about itself.
func (RepeatedGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-repeated-guard",
		Skill:       skills.RepeatedCallHelper{},
		Description: "the same compound `and` condition recurs in 2+ places — it still counts even when reordered, or read through a local variable — and nobody has named it.",
		Rule:        "Name a compound condition you write twice — a property or method on the type it asks about — and ask it by name at every site.",
		Suggestion:  "Move the condition onto the type as `is_…` / `can_…` and replace every copy with the call.",
	}
}
