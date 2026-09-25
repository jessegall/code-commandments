package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MemberAfterMethod is the member-after-method sin.
type MemberAfterMethod struct{}

func init() { sins.Register(catalog.Backend, MemberAfterMethod{}) }

// Definition is what the sin states about itself.
func (MemberAfterMethod) Definition() sins.Definition {
	return sins.Definition{
		Name:        "member-after-method",
		Skill:       skills.ClassLayout{},
		Description: `A trait use, constant, property, property hook, or enum case declared below a method — so the reader only meets that state after seeing the behaviour that uses it.`,
		Rule:        `Declare what a class HAS above what it DOES: trait uses, constants, properties and hooks stand at the top, above the constructor — never between two methods or appended at the bottom.`,
	}
}
