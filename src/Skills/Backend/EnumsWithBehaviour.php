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
            title: "Enums with behaviour — seal the set, put the logic on the type",
            description: "How a closed set of values is modelled — a native backed enum (never raw strings or a const class), with the knowledge keyed off its cases living ON the enum as methods, not re-inlined as a `match`/`switch` at every call site. Read this BEFORE you write a fixed set of string/int values, a `match`/`switch` over an enum (or over strings that mirror one), a `const` class of scalars, or a string field whose values are a closed set.",
            tagline: "A closed set of values is a **type**, and what you *do* per value belongs **on** that type. The smell is
a set expressed as loose strings, or an enum whose cases are matched over and over at the call sites
instead of answering for themselves.",
            summary: "a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).",
            tier: Tier::KeepInMind,
            order: 9,
        );
    }

    public function body(): string
    {
        return <<<'BODY'
## The principle

Two moves, always together:

1. **Seal the set.** A fixed range of values — statuses, kinds, modes — is a **native backed enum**. Not
   raw string literals scattered across comparisons, not a `const` class of scalars, not a `string` field
   that "happens to" hold one of five values.
2. **Put the behaviour on the case.** The knowledge keyed off the set — a per-case value, a per-case
   decision — lives as a **method on the enum**, computed once with an exhaustive `match`. A `match` /
   `switch` over an enum *at a call site* is that method, homeless.

Sealing the set without moving the behaviour just relocates the `match` statements; the win is the enum
*answering for itself*.

## When to use this skill

Reach for this the moment you write:

- a **fixed set of string/int values** used as discrete choices (compared, `in_array`'d, switched on);
- a **`match` / `switch` over an enum** — especially the *same* enum in more than one place;
- a `match` / `switch` over **strings that mirror an enum's cases** (`'pending'`, `'done'` …);
- a **`const` class** of scalar values used as a closed set;
- a **`string`/`int` property** whose value space is actually closed.

## Rule 1 — the set is a native backed enum

```php
// Bad — a closed set as loose strings (and a const class is the same smell)
if ($action->status === 'pending') { ... }
const STATUS_PENDING = 'pending';

// Good — a sealed type
enum TurnStatus: string
{
    case Idle = 'idle';
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
}
```

A `string $status` field that only ever holds those values should be typed `TurnStatus $status`. A `match`
over `'pending'`/`'done'` string literals that mirror an existing enum should dispatch on the enum instead.

## Rule 2 — behaviour lives ON the case, not at the call site

If you're matching an enum to pick a value or a branch, that mapping is the enum's job. Move it onto the
type as a method with an exhaustive `match`; call sites just ask.

```php
// Bad — the same dispatch re-inlined wherever the enum shows up
$class = match ($socket->shape) {
    SocketShape::Data => DataSocket::class,
    SocketShape::Select => SelectSocket::class,
    SocketShape::ResourcePicker => ResourcePickerSocket::class,
};

// Good — the enum answers for itself; every call site is `$socket->shape->portClass()`
enum SocketShape: string
{
    case Data = 'data';
    case Select = 'select';
    case ResourcePicker = 'resource_picker';

    public function portClass(): string
    {
        return match ($this) {
            self::Data => DataSocket::class,
            self::Select => SelectSocket::class,
            self::ResourcePicker => ResourcePickerSocket::class,
        };
    }
}
```

Small constructor-style mappings belong on the enum too — `BoolEnum::fromBool()` / `->toBool()`,
`Status::fromLabel()` — rather than a free function or an inline ternary at each site.

## Rule 3 — `match` is exhaustive; an unhandled case throws

A `match` over an enum lists **every** case (no `default` arm needed — PHP throws `UnhandledMatchError`
on a miss, and adding a new case surfaces every site that must handle it). When you *do* write a
`default`, it **throws a named exception** — never returns `null` / `''` / `[]` to paper over a case you
forgot.

```php
// Bad — a default that swallows an unhandled case into a sentinel
return match ($status) {
    TurnStatus::Done => $result,
    default => null,        // a new case silently returns null
};

// Good — exhaustive, or a throwing default
return match ($status) {
    TurnStatus::Done    => $result,
    TurnStatus::Failed  => throw UnusableTurnException::for($status),
    TurnStatus::Idle, TurnStatus::Pending => throw NotReadyException::for($status),
};
```

(*Whether* an unhandled case is absence vs a thrown invariant is the [`absence`](../absence/SKILL.md) /
[`exceptions`](../exceptions/SKILL.md) call; here the rule is just: never a silent `default`.)

## Rule 4 — name reused subsets; compare null-safe

- **A reused subset of cases gets a name on the enum** — `$status->isTerminal()` rather than
  `in_array($status, [TurnStatus::Done, TurnStatus::Failed], true)` repeated at every site.
- **Comparison goes through the case, null-safe.** Use the `CompareSelf`-style helper anchored on the
  instance (`$socket->type->is(SocketType::Data)`) over chained `===`, so a nullable subject doesn't blow
  up and the intent reads.

## Checklist

```
Enums with behaviour
- [ ] A closed set is a native backed enum — not raw strings, a const class, or an untyped string field.
- [ ] Per-case values/decisions are METHODS on the enum (exhaustive match), not a match re-inlined at call sites.
- [ ] No `match` over strings that mirror an existing enum's cases — dispatch on the enum.
- [ ] `match` is exhaustive; any `default` THROWS a named exception, never returns null/''/[].
- [ ] Reused case subsets are named on the enum; comparison is null-safe (anchored on the instance).
```
BODY;
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
