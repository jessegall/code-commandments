package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MemberOutOfOrder is the member-out-of-order sin.
type MemberOutOfOrder struct{}

func init() { sins.Register(catalog.Backend, MemberOutOfOrder{}) }

// Definition is what the sin states about itself.
func (MemberOutOfOrder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "member-out-of-order",
		Skill:       skills.ClassLayout{},
		Description: `A declaration in the head of a class that arrives after something belonging below it — a constant under a property, a public field under a private one, a hook above the fields it reads`,
		Rule:        `Order the head of a class the same way every time: trait uses, enum cases, constants, static properties, then instance properties public → protected → private, and hooked (derived) properties last, after the fields they read from.`,
	}
}
