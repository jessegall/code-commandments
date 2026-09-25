package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// ArrayReturnBag is the array-return-bag sin.
type ArrayReturnBag struct{}

func init() { sins.Register(catalog.Backend, ArrayReturnBag{}) }

// Definition is what the sin states about itself.
func (ArrayReturnBag) Definition() sins.Definition {
	return sins.Definition{
		Name:        "array-return-bag",
		Skill:       skills.ValueObjects{},
		Description: "Returning a multi-field string-keyed array literal (a bag that should be a value object)",
		Rule:        "Return a typed value object, not a multi-field string-keyed array literal.",
		Suggestion:  "Return a Spatie `Data` object via `::from(...)`.",
	}
}
