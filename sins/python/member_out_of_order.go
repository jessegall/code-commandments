package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MemberOutOfOrder is a constant declared below a field in the head of a class — the inventory read in an ad-hoc order.
type MemberOutOfOrder struct{}

func init() {
	sins.Register(catalog.Python, MemberOutOfOrder{})
}

// Definition is what the sin states about itself.
func (MemberOutOfOrder) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-member-out-of-order",
		Skill:       skills.ClassLayout{},
		Description: "a constant declared below a field in the head of a class — the inventory read in an ad-hoc order",
		Rule:        "Read a class's head in one fixed order: constants (`UPPER_CASE`, `Final`, `ClassVar`) first, then fields.",
		Suggestion:  "Move the constant above the first field.",
	}
}
