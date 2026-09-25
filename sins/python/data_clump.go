package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DataClump is the same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept with no type of its own.
type DataClump struct{}

func init() {
	sins.Register(catalog.Python, DataClump{})
}

// Definition is what the sin states about itself.
func (DataClump) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-data-clump",
		Skill:       skills.ValueObjects{},
		Description: "The same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept with no type of its own.",
		Rule:        "Give values that always travel together one type, and pass that instead of the loose values.",
		Suggestion:  "Declare a frozen dataclass with those fields and take it as one parameter wherever the loose values travelled together.",
	}
}
