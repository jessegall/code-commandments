---
name: commandments-null-object
description: How to model absence with a Null Object or an empty instance instead of null — read before writing or reviewing a `T | null` collection return, a nullable param normalized with `??=`, or a `?->` chain that never branches on null.
---

# Null Object & empty instances over null

## Purpose

Absence does not always need null or an `Option`. When the "missing" case has a
sensible *no-op behaviour* (a do-nothing observer, a no-result callback) or a
natural *empty identity* (an empty array, an empty `Collection`), reach for a
**Null Object** or an **empty instance** instead. Callers then stop null-guarding
and just call through.

## When to use this skill

Pull this skill when you are about to write or review any of:

- a method that returns `T | null` / `?T` where **T is a collection-like type**
  (array, `Collection`, `DataCollection`, `Fluent`, or any
  `Countable`/`Traversable`/`Arrayable`) — an empty instance IS the absence;
- a nullable param `T | null $x = null` whose body **immediately normalizes**
  `$x ??= new Default` / `$x = $x ?? ...` — the signature lies; hoist a Null
  Object default to the signature;
- a nullable property/param consumed via `?->` **two or more times** in a scope
  with no real null branch — the chain means "I never act differently on null",
  so a Null Object removes every `?`.

This is the positive twin of the `PreferNullObjectDefaults` and
`PreferEmptyOverNull` prophets — when one of them fires, it points back here.

## What to read when

| Read | When you need |
|---|---|
| `reference/patterns.md` | The concrete rewrites: empty collection over `?Collection`, Null Object default over a body-normalized nullable param, killing a `?->` chain. The bad → good pairs and the empty-instance picking rules. |
| `reference/vs-option.md` | To choose between a Null Object / empty instance, an `Option<T>`, a plain null default, and a throw. The decision table and the load-bearing "must distinguish ABSENT from EMPTY" carve-out. |

## Backs (prophet family)

Enforced by `PreferNullObjectDefaults` (nullable params normalized in the body;
`?->` chains) and `PreferEmptyOverNull` (`T | null` collection returns). A
finding from either is a pointer back to this skill. For the exact rule text run
`commandments:scripture --prophet=PreferNullObjectDefaults` or
`commandments:scripture --prophet=PreferEmptyOverNull`.
