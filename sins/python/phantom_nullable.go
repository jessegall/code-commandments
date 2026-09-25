package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// PhantomNullable is a field annotated `X | None` that every read assumes is there and none guards — a `None` the design never has.
type PhantomNullable struct{}

func init() {
	sins.Register(catalog.Python, PhantomNullable{})
}

// Definition is what the sin states about itself.
func (PhantomNullable) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-phantom-nullable",
		Skill:       skills.TypeHonesty{},
		Description: "a field annotated `X | None` that every read assumes is there and none guards — a `None` the design never has",
		Rule:        "If a field is used as present everywhere, its type says so: make it required, and fail at construction on a real miss.",
		Suggestion:  "Drop the `| None` and the `= None` default, and make every constructor hand the value over.",
	}
}
