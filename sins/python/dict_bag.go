package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// DictBag is a parameter typed as a dict read by string keys — `row["sku"]`, `row.get("quantity")` — a record nobody declared.
type DictBag struct{}

func init() {
	sins.Register(catalog.Python, DictBag{})
}

// Definition is what the sin states about itself.
func (DictBag) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-dict-bag",
		Skill:       skills.ValueObjects{},
		Description: "A parameter typed as a dict read by string keys — `row[\"sku\"]`, `row.get(\"quantity\")` — a record nobody declared",
		Rule:        "Give a record a type — a frozen dataclass — instead of a dict read by string keys.",
		Suggestion:  "Declare the keys as fields of a frozen dataclass, build it where the data enters (a `from_payload` classmethod), and take that type as the parameter.",
	}
}
