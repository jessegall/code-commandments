package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DanglingDocReference is a Sphinx cross-reference in a docstring (`:class:`shop.cart.Basket“) to a first-party name the codebase no longer declares.
type DanglingDocReference struct{}

func init() {
	sins.Register(catalog.Python, DanglingDocReference{})
}

// Definition is what the sin states about itself.
func (DanglingDocReference) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-dangling-doc-reference",
		Skill:       skills.Documentation{},
		Description: "a Sphinx cross-reference in a docstring (`:class:`shop.cart.Basket``) to a first-party name the codebase no longer declares",
		Rule:        "A cross-reference must resolve: point it at the name that exists now, or delete it.",
		Suggestion:  "Repoint the reference at the current module or class, or remove it.",
	}
}
