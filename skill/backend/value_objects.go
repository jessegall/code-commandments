package backend

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

// ValueObjects teaches: give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.)
type ValueObjects struct{}

func init() {
	skill.Register(catalog.Backend, ValueObjects{})
}

// Definition is what the skill states about itself.
func (ValueObjects) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/value-objects",
		Tier:      skill.Mandatory,
		Order:     3,
		Title:     "Value objects — give related data a type",
		Trigger:   "WHEN to give data a type instead of passing it loose — an `array<string,mixed>` bag, 3+ values that always travel together (a data clump), a string-indexed structured array, primitive obsession, or a too-long parameter list all want a typed object. Read this BEFORE you pass or return an untyped array, add another parameter to a crowded signature, or write `$arr['key']` on a structured array. (How to WRITE the class is `spatie-data`; this is when to make one.)",
		Intro:     valueObjectsIntro,
		Summary:   "give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.)",
		Principle: valueObjectsPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "introduce the type where the data is born, not downstream."},
			{Slug: "backend/spatie-data", Note: `once you've decided it's a DTO, that skill is *how* to write it (and its honest-field-types rule keeps the new type from being a fresh all-nullable bag).`},
			{Slug: "backend/absence", Note: "the new type's fields still answer \"can this be missing?\" honestly."},
		},
	}
}
