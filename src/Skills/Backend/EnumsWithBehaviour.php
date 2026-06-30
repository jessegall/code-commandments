<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class EnumsWithBehaviour extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/enums-with-behaviour',
            tier: Tier::KeepInMind,
            order: 9,
        );
    }

    public function title(): string
    {
        return "Enums with behaviour — seal the set, put the logic on the type";
    }

    public function description(): string
    {
        return "How a closed set of values is modelled — a native backed enum (never raw strings or a const class), with the knowledge keyed off its cases living ON the enum as methods, not re-inlined as a `match`/`switch` at every call site. Read this BEFORE you write a fixed set of string/int values, a `match`/`switch` over an enum (or over strings that mirror one), a `const` class of scalars, or a string field whose values are a closed set.";
    }

    public function intro(): string
    {
        return "A closed set of values is a **type**, and what you *do* per value belongs **on** that type. The smell is
a set expressed as loose strings, or an enum whose cases are matched over and over at the call sites
instead of answering for themselves.";
    }

    public function summary(): string
    {
        return "a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
Two moves, always together:

1. **Seal the set.** A fixed range of values — statuses, kinds, modes — is a **native backed enum**. Not
   raw string literals scattered across comparisons, not a `const` class of scalars, not a `string` field
   that "happens to" hold one of five values.
2. **Put the behaviour on the case.** The knowledge keyed off the set — a per-case value, a per-case
   decision — lives as a **method on the enum**, computed once with an exhaustive `match`. A `match` /
   `switch` over an enum *at a call site* is that method, homeless.

Sealing the set without moving the behaviour just relocates the `match` statements; the win is the enum
*answering for itself*.

### When to use this skill

Reach for this the moment you write:

- a **fixed set of string/int values** used as discrete choices (compared, `in_array`'d, switched on);
- a **`match` / `switch` over an enum** — especially the *same* enum in more than one place;
- a `match` / `switch` over **strings that mirror an enum's cases** (`'pending'`, `'done'` …);
- a **`const` class** of scalar values used as a closed set;
- a **`string`/`int` property** whose value space is actually closed.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            ValueObjects::class => "an enum is the closed-set member of \"give data a type\"; reach for it when the type's values are a fixed set.",
            Absence::class => "a missing/unhandled case is a throw, not a silent `default`.",
            Exceptions::class => "a missing/unhandled case is a throw, not a silent `default`.",
            FixAtTheSource::class => "seal the set where the value is born (a typed enum field) so downstream code never re-parses a string.",
        ];
    }
}
