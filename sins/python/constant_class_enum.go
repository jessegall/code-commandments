package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ConstantClassEnum is a class that is nothing but `PENDING = "pending"` constants — a closed set of values written out by hand instead of an `Enum`.
type ConstantClassEnum struct{}

func init() {
	sins.Register(catalog.Python, ConstantClassEnum{})
}

// Definition is what the sin states about itself.
func (ConstantClassEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-constant-class-enum",
		Skill:       skills.Enums{},
		Description: "a class that is nothing but `PENDING = \"pending\"` constants — a closed set of values written out by hand instead of an `Enum`",
		Rule:        "Seal a closed set of values as an `Enum` or `StrEnum`, so the set is a type and its cases have a home for behaviour.",
		Suggestion:  "`class Status(StrEnum): PENDING = \"pending\"` — then give the per-case knowledge methods on the enum.",
	}
}
