package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// ConstClassEnum is a class that holds nothing but `const` strings or numbers — a closed set of values written as constants instead of an `enum`.
type ConstClassEnum struct{}

func init() {
	sins.Register(catalog.CSharp, ConstClassEnum{})
}

// Definition is what the sin states about itself.
func (ConstClassEnum) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-const-class-enum",
		Skill:       skills.Enums{},
		Description: "a class that holds nothing but `const` strings or numbers — a closed set of values written as constants instead of an `enum`",
		Rule:        "Make a closed set of values an `enum`, not a class of `const` strings or numbers.",
		Suggestion:  "Declare `public enum Status { Pending, Paid }`, and serialise it by name with `JsonStringEnumConverter` where it must still read as its string.",
	}
}
