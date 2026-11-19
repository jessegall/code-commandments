package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UnusedPrivateMember is the unused-private-member sin.
type UnusedPrivateMember struct{}

func init() { sins.Register(catalog.PHP, UnusedPrivateMember{}) }

func (UnusedPrivateMember) Definition() sins.Definition {
	return sins.Definition{
		Name:        "unused-private-member",
		Skill:       skills.TypeHonesty{},
		Description: `A private method nothing in its class calls, or a private property nothing in its class reads: dead code the class still carries.`,
		Rule:        `A class carries only what it uses; a private member nothing reads is dead and is deleted.`,
		Suggestion:  `Delete the member, or call it from where it was meant to be used.`,
	}
}
