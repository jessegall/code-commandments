---
name: commandments-immutable-data
description: How to model data right with Spatie Data — read before writing or reviewing any static fromArray()/fromRow() hydrator, a `new Foo(...)` mapped field-by-field from another object, a Data class without FromArrayOnly, a `#[WithCast] public readonly` property, a hand-rolled Data serialiser, or a `::from()`-in-a-loop collection.
---

# Immutable data — readonly, no manual hydration

## Purpose

A Spatie `Data` class already knows how to be built from an array, copied,
serialised, and collected. Lean on that: declare typed (`readonly` where the
framework lets you) properties, construct via `::from()` / the explicit
`forArray()` entry, and never hand-roll the array↔object mapping, the per-field
serialisation, or the per-element collection hydration that the framework does
declaratively.

## When to use this skill

Pull this skill BEFORE you write or review:

- A static `fromArray()` / `fromRow()` / `fromMetadata()` that reads keys one by
  one and feeds `new self(...)`, or a `new Foo(...)` whose arguments are another
  object's properties listed field-by-field, or a `new Foo(...)` that re-lists
  every field to change one. → backs `NoManualHydration`.
- A class that **extends Spatie `Data` directly** — it needs the `FromArrayOnly`
  trait so `::from()` is guarded to arrays. → backs `DataClassFromArrayOnly`.
- A `readonly` property on a Data class that carries `#[WithCast]` or any other
  value-injecting attribute (the framework cannot inject into `readonly`). →
  backs `ReadonlyDataProperties`.
- A method that reads several properties of a Data-object parameter and assembles
  them into an array — a bespoke serialiser. → backs `PreferDataTransformers`.
- A `foreach` that appends `SomeData::from($row)`, or an `array_map` whose
  callback is `SomeData::from` — collection hydration by hand. → backs
  `PreferDataCollectionOf`.

If the construction site is an array-constructible **bag/Fluent/collection** (not
a Data class), you want the `coalesce-factories` skill instead.

## What to read when

| Read this | When you are… |
|---|---|
| `reference/patterns.md` | Doing any of the above — the bad→good pairs for manual hydration / object mapping / copy-with-changes, the `FromArrayOnly` trait, `readonly` + injecting-attribute placement, `->toArray()` + `#[WithTransformer]` serialisation, and `#[DataCollectionOf]` / `::collect()`, plus the decision table and when-to-leave exemptions. |

## Backs (prophet family)

`ReadonlyDataProperties`, `NoManualHydration`, `DataClassFromArrayOnly`,
`PreferDataTransformers`, `PreferDataCollectionOf`.

A finding from any of these points back here — this skill is the positive
"how to do it right" mirror of what they flag. The `FromArrayOnly` trait the
examples use is the one `commandments:scaffold` generates into
`{{ namespace }}\FromArrayOnly`. For the terse always-on rule of one prophet,
run `commandments:scripture --prophet=<NAME>`.
