package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// LaravelIdioms is the backend/laravel-idioms discipline.
type LaravelIdioms struct{}

func init() { skill.Register(catalog.Backend, LaravelIdioms{}) }

// Definition is what the skill states about itself.
func (LaravelIdioms) Definition() skill.Definition {
	return skill.Definition{
		Slug:    "backend/laravel-idioms",
		Tier:    skill.Mandatory,
		Order:   5,
		Title:   "Laravel idioms — typed access, injected deps, behaviour on the model",
		Trigger: `Use the framework's typed/injected mechanisms and keep behaviour on the model — read request input through TYPED accessors behind named getters (never raw ` + "`" + `->input()` + "`" + `), read a Fluent/ValueBag through typed accessors (never untyped ` + "`" + `->get()` + "`" + `), inject every dependency through the constructor (never ` + "`" + `app()` + "`" + `/facade/` + "`" + `new` + "`" + `), query through named Eloquent scopes (not repeated where-clauses), and mutate through intention-revealing model methods (never a bare ` + "`" + `update([...])` + "`" + ` or ` + "`" + `$model->x = y; save()` + "`" + ` at a call site). Read this BEFORE you call ` + "`" + `->input()` + "`" + `/` + "`" + `->get()` + "`" + `, reach for a dependency, write a query, or update a model.`,
		Intro: `The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model
to hang behaviour on. Reach for those. Raw ` + "`" + `->input()` + "`" + `, untyped ` + "`" + `->get()` + "`" + `, ` + "`" + `app()` + "`" + `-in-a-method, a
repeated ` + "`" + `where()` + "`" + ` chain, and a column-poke-then-` + "`" + `save()` + "`" + ` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.`,
		Summary: `typed request/bag access (never raw ` + "`" + `->input()` + "`" + `/` + "`" + `->get()` + "`" + `), required constructor DI (never ` + "`" + `app()` + "`" + `/facade), Eloquent scopes + intention-revealing model mutation methods.`,
		Principle: `The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model to
hang behaviour on. Reach for those. Raw ` + "`" + `->input()` + "`" + `, an untyped ` + "`" + `->get()` + "`" + `, ` + "`" + `app()` + "`" + `-in-a-method, a ` + "`" + `where()` + "`" + `
chain repeated at call sites, and a column-poke-then-` + "`" + `save()` + "`" + ` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.

Read request input through the request's **typed accessors**, exposed as **named getter methods on the
request class** — the one place the type is settled, so every call site reads a typed value by intent
instead of re-coercing ` + "`" + `mixed` + "`" + `. An MCP tool's input is a request like any other: give each tool its own
named request class (the analogue of a ` + "`" + `FormRequest` + "`" + `), with its keys, rules and types in one place, and
read *that* — never the raw request inside ` + "`" + `handle()` + "`" + `.

Hold every dependency as a required constructor parameter, never resolved by hand from the container.
Express a query concept that recurs across call sites as a **named Eloquent scope**, so the column knowledge
lives in one place instead of being re-typed wherever you query. And mutate a model through
**intention-revealing methods** (` + "`" + `$order->markPaid()` + "`" + `) that say what changed and why — not a bare
` + "`" + `update([...])` + "`" + ` or a set-property-then-` + "`" + `save()` + "`" + ` smeared across the call site.`,
		Languages: []string{"php"},
		Related: []skill.Related{
			{Slug: "backend/value-objects", Reason: `a typed request getter / typed bag read returns the typed value the data should already be; raw ` + "`" + `->input()` + "`" + ` is the loose-array smell at the HTTP edge.`},
			{Slug: "backend/fix-at-the-source", Reason: "read input typed at the boundary so nothing downstream re-coerces a `mixed`."},
			{Slug: "backend/absence", Reason: `a typed accessor for an optional field still answers "can this be missing?" honestly (a nullable getter vs a defaulted one), not a bare ` + "`" + `->input($k, $default)` + "`" + `.`},
		},
	}
}
