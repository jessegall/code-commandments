package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// DictionaryBag is a string-keyed dictionary or JSON object read by keys written in the source — `row["sku"]`, `json.GetProperty("name")` — a record nobody declared.
type DictionaryBag struct{}

func init() {
	sins.Register(catalog.CSharp, DictionaryBag{})
}

// Definition is what the sin states about itself.
func (DictionaryBag) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-dictionary-bag",
		Skill:       skills.ValueObjects{},
		Description: "A string-keyed dictionary or JSON object read by keys written in the source — `row[\"sku\"]`, `json.GetProperty(\"name\")` — a record nobody declared",
		Rule:        "Give a record a type — an immutable `record` — instead of a dictionary read by string keys.",
		Suggestion:  "Declare the keys as members of a record, build it where the data enters (`JsonSerializer.Deserialize<T>` or a static `From` factory), and take that type from there on.",
	}
}
