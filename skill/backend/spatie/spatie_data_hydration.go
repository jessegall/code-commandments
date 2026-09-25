package spatie

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed spatie_data_hydration.intro.md
	spatieDataHydrationIntro string
	//go:embed spatie_data_hydration.principle.md
	spatieDataHydrationPrinciple string
	//go:embed spatie_data_hydration.mechanics.md
	spatieDataHydrationMechanics string
)

// SpatieDataHydration teaches: construct and consume `Data` objects without re-doing what the class declares — pass raw input, let `::from`/casts/collections build.
type SpatieDataHydration struct{}

func init() {
	skill.Register(catalog.Backend, SpatieDataHydration{})
}

// Definition is what the skill states about itself.
func (SpatieDataHydration) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/spatie-data-hydration",
		Tier:      skill.Mandatory,
		Order:     4,
		Title:     "Spatie Data — feed the framework, don't hand-build",
		Trigger:   "How to CONSTRUCT and CONSUME a Spatie `Data` object at a call site without re-doing work the class already declares — the nested `X::from([...])` the parent would hydrate, the `Enum::from($x)`/`new DateTime($x)` a property already casts, the `array_map` filling a `#[DataCollectionOf]`, the hand-rolled `toArray()`, the `#[Computed]` field computed at the call site, the keys remapped by hand. Read this whenever you write or review a `::from([...])` array, a hydrator, or a call that fills a `Data` object.",
		Intro:     spatieDataHydrationIntro,
		Summary:   "construct and consume `Data` objects without re-doing what the class declares — pass raw input, let `::from`/casts/collections build.",
		Principle: spatieDataHydrationPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/spatie-data", Note: "the sibling: how to AUTHOR the `Data` class this skill teaches you to feed — types, `::from` vs `new`, declaring casts/collections."},
		},
		References: []skill.Reference{
			{Name: "mechanics", Title: "Spatie Data hydration mechanics", Body: spatieDataHydrationMechanics},
		},
	}
}
