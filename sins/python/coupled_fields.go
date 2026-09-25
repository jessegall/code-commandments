package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// CoupledFields is a class whose own fields always travel together — assembled into one value again and again, guarded together, or one copying a sibling field's value — one concept held as several fields.
type CoupledFields struct{}

func init() {
	sins.Register(catalog.Python, CoupledFields{})
}

// Definition is what the sin states about itself.
func (CoupledFields) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-coupled-fields",
		Skill:       skills.ValueObjects{},
		Description: "a class whose own fields always travel together — assembled into one value again and again, guarded together, or one copying a sibling field's value — one concept held as several fields.",
		Rule:        "Fields that move as a unit are one type: hold the value object, not its parts; never keep a second copy of what a sibling field already holds.",
		Suggestion:  "Fold the fields into one frozen dataclass (reuse one that already matches, if one exists) and drop a field that just mirrors a sibling's attribute.",
	}
}
