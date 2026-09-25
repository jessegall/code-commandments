package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// MemberAfterMethod is a field, constant or stored property declared below a constructor or a method — the type's state hidden among its behaviour.
type MemberAfterMethod struct{}

func init() {
	sins.Register(catalog.CSharp, MemberAfterMethod{})
}

// Definition is what the sin states about itself.
func (MemberAfterMethod) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-member-after-method",
		Skill:       skills.ClassLayout{},
		Description: "a field, constant or stored property declared below a constructor or a method — the type's state hidden among its behaviour",
		Rule:        "Declare constants, fields and stored properties above the constructor, before any method.",
		Suggestion:  "Move the declaration up to the other state at the top of the type.",
	}
}
