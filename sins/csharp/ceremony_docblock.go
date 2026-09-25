package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// CeremonyDocblock is a doc comment whose every tag is empty or only repeats the signature — `<param name="order">The order.</param>`, an empty `<returns>`.
type CeremonyDocblock struct{}

func init() {
	sins.Register(catalog.CSharp, CeremonyDocblock{})
}

// Definition is what the sin states about itself.
func (CeremonyDocblock) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-ceremony-docblock",
		Skill:       skills.Documentation{},
		Description: "a doc comment whose every tag is empty or only repeats the signature — `<param name=\"order\">The order.</param>`, an empty `<returns>`",
		Rule:        "A doc comment must say something the signature does not; drop tags that only repeat a name or a type.",
		Suggestion:  "Delete the comment, or write the sentence that says what the member does and describe only what a name and a type cannot.",
	}
}
