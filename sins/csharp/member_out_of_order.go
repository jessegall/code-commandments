package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// MemberOutOfOrder is a `const` or `static readonly` value declared below a field or a stored property — the top of the type read in no particular order.
type MemberOutOfOrder struct{}

func init() {
	sins.Register(catalog.CSharp, MemberOutOfOrder{})
}

// Definition is what the sin states about itself.
func (MemberOutOfOrder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-member-out-of-order",
		Skill:       skills.ClassLayout{},
		Description: "a `const` or `static readonly` value declared below a field or a stored property — the top of the type read in no particular order",
		Rule:        "Declare constants first, then fields, then stored properties.",
		Suggestion:  "Move the constant up above the fields.",
	}
}
