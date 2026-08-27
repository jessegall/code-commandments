---
name: commandments-backend-laravel-idioms
description: "Use the framework's typed/injected mechanisms and keep behaviour on the model — read request input through TYPED accessors behind named getters (never raw `->input()`), read a Fluent/ValueBag through typed accessors (never untyped `->get()`), inject every dependency through the constructor (never `app()`/facade/`new`), query through named Eloquent scopes (not repeated where-clauses), and mutate through intention-revealing model methods (never a bare `update([...])` or `$model->x = y; save()` at a call site). Read this BEFORE you call `->input()`/`->get()`, reach for a dependency, write a query, or update a model."
---

# Laravel idioms — typed access, injected deps, behaviour on the model

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model
> to hang behaviour on. Reach for those. Raw `->input()`, untyped `->get()`, `app()`-in-a-method, a
> repeated `where()` chain, and a column-poke-then-`save()` are all the same mistake: throwing away a
> type, a wire, or a name the framework was holding for you.

## The principle

The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model to
hang behaviour on. Reach for those. Raw `->input()`, an untyped `->get()`, `app()`-in-a-method, a `where()`
chain repeated at call sites, and a column-poke-then-`save()` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.

Read request input through the request's **typed accessors**, exposed as **named getter methods on the
request class** — the one place the type is settled, so every call site reads a typed value by intent
instead of re-coercing `mixed`. An MCP tool's input is a request like any other: give each tool its own
named request class (the analogue of a `FormRequest`), with its keys, rules and types in one place, and
read *that* — never the raw request inside `handle()`.

Hold every dependency as a required constructor parameter, never resolved by hand from the container.
Express a query concept that recurs across call sites as a **named Eloquent scope**, so the column knowledge
lives in one place instead of being re-typed wherever you query. And mutate a model through
**intention-revealing methods** (`$order->markPaid()`) that say what changed and why — not a bare
`update([...])` or a set-property-then-`save()` smeared across the call site.

## Rules

- [ ] Inject a typed config object; never read `config('…')` inside a class.
      _Inject a typed config value object._
- [ ] Declare dependencies in the constructor; never reach into the container with `app()`/`resolve()` from a resolved class.
      _Declare the dependency as a constructor parameter._
- [ ] Config is an interface: every key exists because something reads it. When the last reader goes, the key goes with it.
      _Delete the key (and its env var), or restore the reader the feature lost._
- [ ] A listener exists to answer a dispatch. When the last dispatcher goes, the listener goes with it.
      _Delete the registration (and the listener, if nothing else reaches it) — or restore the dispatch the listener was waiting for._
- [ ] The config FILE owns the default. A reader asks for the value; it does not restate what the value should be when absent.
      _Drop the reader's fallback and let the config file answer — or delete the key from the file and let the reader's default be the one truth._
- [ ] Inject the dependency; never call a Laravel facade (`Cache::`, `Log::`, `Mail::`) inside a class.
      _Constructor-inject the dependency behind its interface._
- [ ] Mutate a model through an intention method; never `$model->update([...])` an anonymous array of columns at a call site.
      _An intention method on the model (`$order->markPaid()`)._
- [ ] Mutate a model through an intention method; don't set-property-then-`save()` at a call site.
      _An intention method on the model (`$order->suspend($reason)`)._
- [ ] Wiring is code: a binding exists to answer a resolve. When the last consumer goes, the binding goes with it.
      _Delete the registration (and the implementation it names, if that is dead too)._
- [ ] Read request input through a typed accessor (`$request->string('x')`); never raw `->input()`/`->get()`/`->query()`.
      _A named getter on a `FormRequest` subclass (`$request->productId()`)._
- [ ] Expose a named getter on a typed request class; don't re-coerce a typed accessor (`$request->string('id')->toString()`) at a call site.
      _A named getter on a typed request class returning the coerced value._

## Worked example

### config-read

`config('…')` read inside a class

```php
----------[ Bad ]----------

public function search(array $filters): array
{
    $perPage = config('shop.catalog.per_page');

    // formerly filtered in PHP; refactored into the query builder in v3
    $term = $filters['q'];
    $sort = $filters['sort'];

    return $this->run($term, $sort, $perPage);
}

----------[ Good ]----------

// in Shop\Catalog\CatalogSearchService
// Injects the typed settings object instead of reading config in the body.

public function searchTop(string $term, string $sort): array
{
    return $this->run($term, $sort, $this->settings->perPage);
}

// The config values the catalog actually uses, read once at the edge and typed. Every body that
// used to reach for `config()` takes this instead, and gets an `int` rather than a `mixed`.

final class CatalogSettings
{
    public function __construct(
        public readonly int $perPage = 24,
    ) {}
}
```

The other 10 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/laravel-idioms` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `config-read`, `container-reach`, `dead-config-key`, `dead-event-wiring`, `duplicated-config-default`, `facade-call`, `mass-update-at-call-site`, `model-mutation-at-call-site`, `orphaned-binding`, `raw-request-input`, `request-accessor-recast`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 11 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/value-objects`](../value-objects/SKILL.md) — a typed request getter / typed bag read returns the typed value the data should already be; raw `->input()` is the loose-array smell at the HTTP edge.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — read input typed at the boundary so nothing downstream re-coerces a `mixed`.
- [`backend/absence`](../absence/SKILL.md) — a typed accessor for an optional field still answers "can this be missing?" honestly (a nullable getter vs a defaulted one), not a bare `->input($k, $default)`.
