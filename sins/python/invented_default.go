package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// InventedDefault is `f(x or "")` — an empty string, `0` or `False` invented to fill an argument when the value is missing, a stand-in the callee cannot tell from real data.
type InventedDefault struct{}

func init() {
	sins.Register(catalog.Python, InventedDefault{})
}

// Definition is what the sin states about itself.
func (InventedDefault) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-invented-default",
		Skill:       skills.Absence{},
		Description: "`f(x or \"\")` — an empty string, `0` or `False` invented to fill an argument when the value is missing, a stand-in the callee cannot tell from real data",
		Rule:        "Never fill an argument with an invented `\"\"`, `0` or `False` on absence — handle the missing case, or make the value certain where it is born.",
		Suggestion:  "Decide at the source: raise when the value must be there, or pass `None` on to a parameter that admits it. A real default (`or \"EUR\"`) is a choice, not an invention.",
	}
}
