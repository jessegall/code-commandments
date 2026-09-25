package spatie

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed spatie_data.intro.md
	spatieDataIntro string
	//go:embed spatie_data.principle.md
	spatieDataPrinciple string
)

// SpatieData teaches: how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.
type SpatieData struct{}

func init() {
	skill.Register(catalog.Backend, SpatieData{})
}

// Definition is what the skill states about itself.
func (SpatieData) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/spatie-data",
		Tier:      skill.Mandatory,
		Order:     4,
		Title:     "Spatie Data — let the class build itself",
		Trigger:   "How to write and construct a Spatie Data class — final, public readonly promoted props, honest field types, `::from([...])` rather than `new`, `from<Type>` magic factories, `#[DataCollectionOf]` collections, `Optional` vs `?T` vs a default, mapping, casts and validation. Read this FIRST whenever you write or review a `Data` class, a `::from`, a `new SomeData`, a hydrator that fills fields one by one, an `@method from`/`collect` docblock hint, or a `#[...]` attribute on a Data property.",
		Intro:     spatieDataIntro,
		Summary:   "how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.",
		Principle: spatieDataPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: `a Data class IS a boundary; type it total so consumers don't re-validate. The all-nullable DTO is the canonical symptom-deferral.`},
			{Slug: "backend/absence", Note: "owns the `Optional` vs `?T` vs default decision; this skill only gives the Spatie mechanics for each."},
			{Slug: "backend/exceptions", Note: "a required field missing at `::from()` should fail hard; surface it named when you catch the framework's exception at a tolerant boundary."},
		},
	}
}
