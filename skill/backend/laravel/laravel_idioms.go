package laravel

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed laravel_idioms.intro.md
	laravelIdiomsIntro string
	//go:embed laravel_idioms.principle.md
	laravelIdiomsPrinciple string
)

// LaravelIdioms teaches: typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.
type LaravelIdioms struct{}

func init() {
	skill.Register(catalog.Backend, LaravelIdioms{})
}

// Definition is what the skill states about itself.
func (LaravelIdioms) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/laravel-idioms",
		Tier:      skill.Mandatory,
		Order:     5,
		Title:     "Laravel idioms — typed access, injected deps, behaviour on the model",
		Trigger:   "Use the framework's typed/injected mechanisms and keep behaviour on the model — read request input through TYPED accessors behind named getters (never raw `->input()`), read a Fluent/ValueBag through typed accessors (never untyped `->get()`), inject every dependency through the constructor (never `app()`/facade/`new`), query through named Eloquent scopes (not repeated where-clauses), and mutate through intention-revealing model methods (never a bare `update([...])` or `$model->x = y; save()` at a call site). Read this BEFORE you call `->input()`/`->get()`, reach for a dependency, write a query, or update a model.",
		Intro:     laravelIdiomsIntro,
		Summary:   "typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.",
		Principle: laravelIdiomsPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/value-objects", Note: "a typed request getter / typed bag read returns the typed value the data should already be; raw `->input()` is the loose-array smell at the HTTP edge."},
			{Slug: "backend/fix-at-the-source", Note: "read input typed at the boundary so nothing downstream re-coerces a `mixed`."},
			{Slug: "backend/absence", Note: "a typed accessor for an optional field still answers \"can this be missing?\" honestly (a nullable getter vs a defaulted one), not a bare `->input($k, $default)`."},
		},
	}
}
