package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// UndeclaredMember is the undeclared-member sin.
type UndeclaredMember struct{}

func init() { sins.Register(catalog.PHP, UndeclaredMember{}) }

func (UndeclaredMember) Definition() sins.Definition {
	return sins.Definition{
		Name:        "undeclared-member",
		Skill:       skills.TypeHonesty{},
		Description: `A property read or method call the value's declared class or interface neither declares nor inherits: it holds for some implementers, and only a docblock or luck promises it`,
		Rule:        `Read only what the declared type guarantees. When some implementers carry the member, give them an interface that declares it and type the value by that interface.`,
		Suggestion:  "declare the member on an interface the carrying classes implement, and type the parameter by it",
	}
}

func (UndeclaredMember) Unpublished() {}
