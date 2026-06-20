---
name: commandments-enums
description: How to turn closed-set strings into enums and put behaviour on the case — read before writing or reviewing a `string $status`/`$direction`/`$kind` field, a `match`/`switch`/`if` chain on string or enum-case literals, a `$x === Enum::Case` comparison, or an inlined `[Enum::A, Enum::B, …]` group.
---

# Enums — strings to types, behaviour on the case

## Purpose

A value with a known, finite set of cases is an enum, not a `string`. Once it is
an enum, its per-case behaviour and its named subsets belong **on** the enum —
dispatch on the type, never re-derive it at the call site with an inline `match`
or a hand-built case array. Comparisons route through the null-safe `CompareSelf`
trait instead of raw `===`/`!==`.

## When to use this skill

Reach for this skill when you are about to write — or are reviewing — any of:

- a `string` / `?string` field named `$status`, `$direction`, `$kind`, `$mode`,
  `$type`, `$state`, `$role`, `$format`, … whose values are a known finite set —
  or a `string` literal default / named arg / closed call-site set that is really
  a case in disguise. → read `reference/strings-to-enums.md`.
- a `match` / `switch` / `if`-`elseif` branching on string literals
  (`'input'`, `'output'`, …) that are an enum's cases. → read
  `reference/strings-to-enums.md`.
- a `match` / `switch` on an enum where every arm maps a case to a **value or
  per-case behaviour** — that dispatch belongs on the type, as an enum method or
  (when arms call collaborators) a strategy object. → read
  `reference/behavioural-dispatch.md`.
- a recognisable **subset** of an enum's cases inlined as an array
  (`[Status::A, Status::B, Status::C]`), especially if it appears in more than
  one place. → read `reference/case-groups.md`.
- a raw `$x === Enum::Case` / `$x !== Enum::Case` comparison, or a `||`/`&&`
  chain of them, or a wrong-form static `Enum::equals($x, Enum::Case)`. → read
  `reference/behavioural-dispatch.md` (CompareSelf section).

This is the positive twin of the enum prophet family below — when one of them
fires, it points back here.

## What to read when

| Read | When you need |
|---|---|
| `reference/strings-to-enums.md` | To turn a stringly-typed closed set into an enum: a `string` field whose name/values denote a finite set, a literal default or named arg, a closed set of call-site values, or a `match`/`switch`/`if` branching on string literals. Includes the reuse-vs-create-a-new-enum decision. |
| `reference/behavioural-dispatch.md` | To move per-case logic that lives in the caller onto the type: a value/label `match` → an enum method; a wide behavioural `match` (collaborators) → strategy objects + a registration map; and the `CompareSelf` rewrites for raw enum comparisons. |
| `reference/case-groups.md` | When a named subset of an enum's cases is inlined as an array — give it a named accessor (`Enum::numeric(): array`) on the enum and call that instead of re-inlining the group. |

## Backs (prophet family)

Enforced by **StringsThatShouldBeEnums** (literal closed sets), **PreferEnumForClosedSetField**
(`string` field whose name denotes a closed set), **PreferTypeMethodOverInlineDispatch**
(value/label dispatch belongs on the enum), **BehaviouralEnumDispatch** (wide
behavioural dispatch → strategy objects), **PreferEnumCaseGroups** (named subset →
enum accessor), and **SuggestCompareSelfTrait** (raw `===`/`!==` → the null-safe
`equals` family). A finding from any of them points back to this skill. For the
exact rule text run `commandments:scripture --prophet=<NAME>` (e.g.
`--prophet=SuggestCompareSelfTrait`).
