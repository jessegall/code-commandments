package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// CeremonyDocblock is a docstring with no summary whose every entry restates the annotated signature — `order (Order):`, `:rtype: int`.
type CeremonyDocblock struct{}

func init() {
	sins.Register(catalog.Python, CeremonyDocblock{})
}

// Definition is what the sin states about itself.
func (CeremonyDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-ceremony-docblock",
		Skill:       skills.Documentation{},
		Description: "a docstring with no summary whose every entry restates the annotated signature — `order (Order):`, `:rtype: int`",
		Rule:        "A docstring must add meaning beyond the signature; drop entries that only repeat an annotation.",
		Suggestion:  "Delete the docstring, or write the sentence that says what the function does and describe only what a type cannot.",
	}
}
