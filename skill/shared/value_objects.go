package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed value_objects.md
var valueObjectsBody string

// ValueObjects teaches giving related data a named, immutable type in every language, instead of a loose map, a positional list or parameters that travel together.
type ValueObjects struct{}

func init() {
	skill.Register(catalog.Shared, ValueObjects{})
}

func (ValueObjects) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/value-objects",
		Tier:      skill.Mandatory,
		Order:     5,
		Title:     "Value objects — give related data a type",
		Trigger:   "Passing or returning a map or array whose string keys are a fixed record, reading `row[\"field\"]` off data your own code built, returning several values as a list the caller unpacks by position, adding a parameter that always travels with two others, fields that always change together, or a method that changes a value in place. Read this BEFORE you shape data as a bag or grow a signature, and when a value-objects finding points here.",
		Summary:   "give related data a named type, built complete and never changed: no map read by string keys, no positional tuple, no group of parameters that always travel together, no fields that move as one without a type, and a new value derived instead of a field written.",
		Body:      valueObjectsBody,
		Formerly:  []string{"php/value-objects", "csharp/value-objects", "python/value-objects"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "php/spatie-data", Note: "how to write the type when it crosses a boundary in a Laravel project."},
			{Slug: "shared/type-honesty", Note: "a value object's fields are declared as what they always are."},
		},
	}
}
