package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed value_objects.intro.md
	valueObjectsIntro string
	//go:embed value_objects.principle.md
	valueObjectsPrinciple string
)

// ValueObjects teaches: give related data a type — an immutable `record` built at the edge — instead of a dictionary read by fixed string keys, or values that always travel together.
type ValueObjects struct{}

func init() {
	skill.Register(catalog.CSharp, ValueObjects{})
}

// Definition is what the skill states about itself.
func (ValueObjects) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/value-objects",
		Tier:      skill.Mandatory,
		Order:     33,
		Title:     "C# value objects — give related data a type",
		Trigger:   "Passing related data around in C# as a `Dictionary<string, object>` or `Dictionary<string, string>` read by fixed keys (`row[\"sku\"]`), a `JsonNode`/`JsonElement` read field by field, a tuple returned and destructured, or the same three or four parameters handed from method to method side by side. Read this BEFORE writing any of them.",
		Intro:     valueObjectsIntro,
		Summary:   "give related data a type — an immutable `record` built at the edge — instead of a dictionary read by fixed string keys, or values that always travel together.",
		Principle: valueObjectsPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/value-objects", Note: "the same discipline on the PHP backend, with Spatie `Data`."},
			{Slug: "python/value-objects", Note: "the same discipline in Python, with frozen dataclasses."},
			{Slug: "csharp/absence", Note: "what a member of the new type holds when its value may be missing."},
		},
	}
}
