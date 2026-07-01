<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend\Laravel;

use JesseGall\CodeCommandments\Skills\Backend\Absence;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class LaravelIdioms extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/laravel-idioms',
            tier: Tier::Mandatory,
            order: 5,
        );
    }

    public function title(): string
    {
        return "Laravel idioms — typed access, injected deps, behaviour on the model";
    }

    public function description(): string
    {
        return "Use the framework's typed/injected mechanisms and keep behaviour on the model — read request input through TYPED accessors behind named getters (never raw `->input()`), read a Fluent/ValueBag through typed accessors (never untyped `->get()`), inject every dependency through the constructor (never `app()`/facade/`new`), query through named Eloquent scopes (not repeated where-clauses), and mutate through intention-revealing model methods (never a bare `update([...])` or `\$model->x = y; save()` at a call site). Read this BEFORE you call `->input()`/`->get()`, reach for a dependency, write a query, or update a model.";
    }

    public function intro(): string
    {
        return "The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model
to hang behaviour on. Reach for those. Raw `->input()`, untyped `->get()`, `app()`-in-a-method, a
repeated `where()` chain, and a column-poke-then-`save()` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.";
    }

    public function summary(): string
    {
        return "typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
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
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            ValueObjects::class => "a typed request getter / typed bag read returns the typed value the data should already be; raw `->input()` is the loose-array smell at the HTTP edge.",
            FixAtTheSource::class => "read input typed at the boundary so nothing downstream re-coerces a `mixed`.",
            Absence::class => "a typed accessor for an optional field still answers \"can this be missing?\" honestly (a nullable getter vs a defaulted one), not a bare `->input(\$k, \$default)`.",
        ];
    }
}
