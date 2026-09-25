package spatie

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed page_objects.intro.md
	pageObjectsIntro string
	//go:embed page_objects.principle.md
	pageObjectsPrinciple string
)

// PageObjects teaches: the composed `Data` a controller returns for a page — container-injected + `#[Hidden]` collaborators, computed slots over a fat constructor, transformers for output shape.
type PageObjects struct{}

func init() {
	skill.Register(catalog.Backend, PageObjects{})
}

// Definition is what the skill states about itself.
func (PageObjects) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/page-objects",
		Tier:      skill.Mandatory,
		Order:     5,
		Title:     "Page Objects — the Data class builds the whole page",
		Trigger:   "How to write a PAGE OBJECT — the composed Spatie `Data` a controller returns for one page to render, assembling several nested Data slots and travelling back in the response. Read this BEFORE you write or review a `*Page`/view-model `Data` class, a controller that returns a Data payload, a page object's constructor, or a `#[FromContainer]`/`#[Hidden]`/`#[Computed]`/`#[WithTransformer]` attribute on a Data property. Read it too when you are deciding how a Data property should be SHAPED on the wire — a `Money`, a `Carbon`, an enum, any value object whose serialized form must differ from its PHP type — or when you are about to reach for `app()`/`resolve()`/a facade inside a Data class.",
		Intro:     pageObjectsIntro,
		Summary:   "the composed `Data` a controller returns for a page — container-injected + `#[Hidden]` collaborators, computed slots over a fat constructor, transformers for output shape.",
		Principle: pageObjectsPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/spatie-data", Note: "the mechanics of a `Data` class this builds on — `::from()`, honest types, `#[DataCollectionOf]`; a page object is a Data used as a composed view-model."},
			{Slug: "backend/laravel-idioms", Note: "owns the general 'no service location — inject through the container' rule; a page object's `#[FromContainer]` is that rule applied to a Data."},
			{Slug: "backend/fix-at-the-source", Note: `a page object is a boundary; build each slot where it is projected, not by threading half-built state through a constructor.`},
		},
	}
}
