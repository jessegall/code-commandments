---
name: commandments-coalesce-factories
description: How to construct typed values right — hoist scattered `?? []` / `new Bag($v ?? [])` / `is_numeric($x) ? (int) $x : $d` ceremony onto a `::coalesce()` factory or a `T_*` helper. Read before writing or reviewing any null-coalesce into an empty literal, a value object built from a nullable array, or a repeated cast-with-fallback.
---

# Coalesce factories & typed value construction

## Purpose

Defaulting and coercing a loose value into its real type is a one-place job, not
a per-call-site one. This subject covers where that logic belongs: a total
`::coalesce()` factory on the value object, the typed `T_*::coalesce()` /
`T_*::coalesceFor()` / `T_*::coerce()` helpers from php-types, and when to leave
a plain `?? default` alone.

## When to use this skill

Reach for this skill when you are about to write — or are reviewing — any of:

- `new ValueBag($v ?? [])`, `new Bag(is_array($v) ? $v : [])`, or
  `Bag::make(T_Array::coalesce($v))` — a value object built from a nullable /
  shape-guarded **array** inline. → read `reference/factories.md`.
- `$x ?? []` / `?? ''` / `?? 0` / `?? false` (or `?? T_Array::EMPTY` and the
  other `T_*` empty constants) on a nullable typed value. → read
  `reference/type-coalesce.md`.
- `T_Array::coalesce($arr[$key] ?? null)` — a double-coalesced dynamic
  dictionary lookup. → read `reference/type-coalesce.md`.
- The same `is_numeric($x) ? (int) $x : $default` cast-with-fallback ternary
  hand-rolled in two or more methods of one class. → read
  `reference/type-coalesce.md`.

This pairs with the **coalesce / type-construction prophet family** below — the
enforcement side of the same rules.

## What to read when

| Read | When you are dealing with |
|---|---|
| `reference/factories.md` | A **value object** (array-constructible bag / Fluent / collection / your own `__construct(array …)`) built from a nullable or shape-guarded array. The fix is a `::coalesce()` factory on the class. |
| `reference/type-coalesce.md` | **Scalar / array** defaulting and coercion: `?? <empty literal>` → `T_*::coalesce()`, double-coalesced dictionary lookups → `T_Array::coalesceFor()`, and repeated cast-with-fallback ternaries → `T_*::coerce()`. |

## Backs (prophet family)

Enforced by **PreferCoalesceFactory** (value-object factory),
**PreferTypeCoalesce** (`?? <empty>` → `T_*::coalesce()`),
**PreferCoalesceFor** (double-coalesced dictionary lookup → `T_*::coalesceFor()`),
and **PreferCoercionHelper** (repeated cast-with-fallback → `T_*::coerce()`). A
finding from any of them points back to this skill. For the exact rule text run
`commandments:scripture --prophet=<NAME>` (e.g. `--prophet=PreferCoalesceFactory`).
