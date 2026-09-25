package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// MemberAfterMethod is a constant, class attribute or field declared below a method — the class's state hidden among its behaviour.
type MemberAfterMethod struct{}

func init() {
	sins.Register(catalog.Python, MemberAfterMethod{})
}

// Definition is what the sin states about itself.
func (MemberAfterMethod) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-member-after-method",
		Skill:       skills.ClassLayout{},
		Description: "a constant, class attribute or field declared below a method — the class's state hidden among its behaviour",
		Rule:        "Declare a class's state at the top — constants, class attributes and fields above `__init__` and every method.",
		Suggestion:  "Move the assignment up to the head of the class, with the other state.",
	}
}
