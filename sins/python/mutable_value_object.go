package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MutableValueObject is a dataclass whose own methods write the fields it was built from after construction — a value that changes under everyone holding it.
type MutableValueObject struct{}

func init() {
	sins.Register(catalog.Python, MutableValueObject{})
}

// Definition is what the sin states about itself.
func (MutableValueObject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-mutable-value-object",
		Skill:       skills.ValueObjects{},
		Description: "a dataclass whose own methods write the fields it was built from after construction — a value that changes under everyone holding it",
		Rule:        "Make a value immutable: build it complete and derive a new one to change it; never write its fields after construction.",
		Suggestion:  "`@dataclass(frozen=True)` and `return replace(self, amount=…)` from the method that changed it.",
	}
}
