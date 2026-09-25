package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// ConstantProperty is an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one.
type ConstantProperty struct{}

func init() {
	sins.Register(catalog.Python, ConstantProperty{})
}

// Definition is what the sin states about itself.
func (ConstantProperty) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-constant-property",
		Skill:       skills.TypeHonesty{},
		Description: "an `@property` whose body never reads `self` — `return \"box\"` — a stored value made to look like a computed one.",
		Rule:        "A `@property` must derive from the object; a value it never reads `self` for is a class attribute.",
		Suggestion:  "`kind = \"box\"` on the class — or a `ClassVar` — and the property goes.",
	}
}
