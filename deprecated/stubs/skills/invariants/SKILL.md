---
name: commandments-invariants
description: Tell genuine absence apart from an invariant violation and fail loud — read before writing or reviewing any nullable return, a `match` with a `default => null`, a `catch (…NotFound) { return null; }`, or any "no value" that can only mean a wiring bug.
---

# Absence vs invariant violation — fail loud

## Purpose

Not every `null` is the same. A lookup that *can* legitimately miss is genuine
absence — model it with `{{ namespace }}\Option`. A `null` that can only appear
if *you* made a mistake (a forgotten enum case, an unregistered handler, a
"not found" the caller already guaranteed must exist) is an **invariant
violation**. Modelling that as `?T` / `Option<T>` silently swallows a bug and
forces every caller to null-guard a state that should have crashed. This skill
is the decision: which kind of absence is this, and when the answer is
"invariant violation", how to fail loud instead.

## When to use this skill

Pull this skill when you are about to write, or are reviewing, any of:

- A `match`/`switch` over a closed-set enum with a `default => null` or
  `default => Option::none()` arm.
- A method whose return type is `?T`, `T|null`, or `Option<T>` — *before* you
  commit to the nullability, check whether absence is ever actually handled.
- A `try { … } catch (SomethingNotFound) { $x = null; }` (or `= false` / `= []`)
  that swallows a miss into a sentinel.
- A lookup pair — naming the `find` / `has` / `get` companions, deciding which
  one returns an Option and which one throws.

The principle in one line: **reserve `Option`/null for absence that is possible
from valid input; everything else should fail loud at the source.**

## What to read when

| Read this reference | When you are… |
|---|---|
| `reference/absence-taxonomy.md` | Unsure which of the three kinds of absence you have (genuine / no-absence / invariant-violation) and which tool each one wants. Start here. |
| `reference/fail-loud-patterns.md` | Turning an invariant-violation absence into the right loud form — an exhaustive `match`, a thrown named exception, or a total method. |
| `reference/require-companions.md` | Designing or fixing a `find` / `has` / `get` lookup API: which companion returns an Option and which throws, and how `…OrFail` companions read. |

## Backs (prophet family)

This skill is the positive mirror of these enforcing prophets — a finding from
any of them points back here:

- **ThrowOnUnhandledCaseProphet** — a closed-enum `match` whose only `null`/`none`
  is the `default`/`null` arm (a forgotten case modelled as absence).
- **PreferTotalOverNullableProphet** — a private method returns nullable but every
  caller immediately de-nulls it; make it total or throw at the source.
- **NoSwallowedNotFoundProphet** — a `catch (…NotFound)` that does nothing but
  swallow the miss into `null`/`false`/`[]`.

Read the exact rule with `commandments:scripture --prophet=ThrowOnUnhandledCase`
(or `PreferTotalOverNullable`, `NoSwallowedNotFound`).
