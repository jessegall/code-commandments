---
name: commandments-php-enums-with-behaviour
description: "How a closed set of values is modelled — a native backed enum (never raw strings or a const class), with the knowledge keyed off its cases living ON the enum as methods, not re-inlined as a `match`/`switch` at every call site. Read this BEFORE you write a fixed set of string/int values, a `match`/`switch` over an enum (or over strings that mirror one), a `const` class of scalars, or a string field whose values are a closed set."
---

# Enums with behaviour — seal the set, put the logic on the type

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A closed set of values is a **type**, and what you *do* per value belongs **on** that type. The smell is
> a set expressed as loose strings, or an enum whose cases are matched over and over at the call sites
> instead of answering for themselves.

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

### When to use this skill

Reach for this the moment you write:

- a **fixed set of string/int values** used as discrete choices (compared, `in_array`'d, switched on);
- a **`match` / `switch` over an enum** — especially the *same* enum in more than one place;
- a `match` / `switch` over **strings that mirror an enum's cases** (`'pending'`, `'done'` …);
- a **`const` class** of scalar values used as a closed set;
- a **`string`/`int` property** whose value space is actually closed.

## Rules

- [ ] Use the enum's comparison method when the comparison carries no type narrowing.
      _Ask the enum to compare itself with the case._
- [ ] Put per-case behaviour on the enum; never `match`/`switch` over its `->value` at a call site.
      _A method on the backed enum (`$x->label()`, `$x->isPaid()`)._

## Worked example

### compare-self

A strict comparison bypasses an enum's verified comparison method.

```php
----------[ Bad ]----------

function hasDispatched(Dispatch $dispatch): bool
{
    return $dispatch !== Dispatch::Draft && $dispatch !== Dispatch::Pending;
}

----------[ Good ]----------

function alreadyDispatched(Dispatch $dispatch): bool
{
    return $dispatch->notEqualsAny(Dispatch::Draft, Dispatch::Pending);
}
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=php/enums-with-behaviour` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `compare-self`, `enum-value-match`.
- `{{binary}} repent --sin=<sin>` — auto-fix, for `compare-self`. Review it with `--dry-run` first.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/value-objects`](../value-objects/SKILL.md) — an enum is the closed-set member of "give data a type"; reach for it when the type's values are a fixed set.
- [`php/absence`](../absence/SKILL.md) — a missing/unhandled case is a throw, not a silent `default`.
- [`php/exceptions`](../exceptions/SKILL.md) — a missing/unhandled case is a throw, not a silent `default`.
- [`php/fix-at-the-source`](../fix-at-the-source/SKILL.md) — seal the set where the value is born (a typed enum field) so downstream code never re-parses a string.
