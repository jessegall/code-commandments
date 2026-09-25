package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// RepeatedTypeGuard is the same multi-`isinstance` narrowing (`isinstance(x, A) and isinstance(x.y, B)`) is written in 2+ places — a check on a shape that nobody has named.
type RepeatedTypeGuard struct{}

func init() {
	sins.Register(catalog.Python, RepeatedTypeGuard{})
}

// Definition is what the sin states about itself.
func (RepeatedTypeGuard) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-repeated-type-guard",
		Skill:       skills.RepeatedCallHelper{},
		Description: "the same multi-`isinstance` narrowing (`isinstance(x, A) and isinstance(x.y, B)`) is written in 2+ places — a check on a shape that nobody has named.",
		Rule:        "Name a type narrowing you write twice — a method, a property or a `TypeGuard` function — and ask for the shape by name.",
		Suggestion:  "Move the chain into one named predicate (a `TypeGuard` where the caller needs the narrowed type) and call it at every site.",
	}
}
