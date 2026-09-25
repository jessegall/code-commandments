package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// PlaceholderFilledData is `Card(title=…, body="")` — a dataclass field required as `str` handed the blank to satisfy the signature, a value the type cannot catch.
type PlaceholderFilledData struct{}

func init() {
	sins.Register(catalog.Python, PlaceholderFilledData{})
}

// Definition is what the sin states about itself.
func (PlaceholderFilledData) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-placeholder-filled-data",
		Skill:       skills.TypeHonesty{},
		Description: "`Card(title=…, body=\"\")` — a dataclass field required as `str` handed the blank to satisfy the signature, a value the type cannot catch",
		Rule:        "A required field means the caller has the value; never fill one with `\"\"` to satisfy the signature.",
		Suggestion:  "Fetch the real value — or split a narrower dataclass that only promises what this caller knows.",
	}
}
