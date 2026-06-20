---
name: commandments-option
description: How to do absence right with Option<T> — read before writing or reviewing any nullable return, `getOr(null)`, `?? null`, Option-in-a-union, or an `isEmpty()`-guard-then-unwrap.
---

# Option&lt;T&gt; — present-or-absent without null

## Purpose

`Option<T>` puts a value's presence into the type instead of a null you must
remember to check. A method returns `Option<T>`, never `T|null`; callers stay
inside the Option (`map`/`transform`/`each`/`andThen`/`getOr`/`getOrThrow`)
instead of re-deriving "is it there?" at every call site.

## When to use this skill

Pull this skill whenever you are about to write or review:

- A method that **sometimes returns a value and sometimes returns null** — and
  has more than one or two callers (the empty case is a real type, not a hidden
  branch). → backs `PreferOptionOverNull`.
- Any **unwrap of an Option**: `getOr(null)`, an `isEmpty()` guard followed by
  `getOrThrow()`, or a `transform(...)->getOr(Option::none())`. → backs
  `NoOptionToNull`, `UnwrapOptionWithGuard`, `PreferOptionChainOverGuard`,
  `PreferAndThen`.
- A `?? null` fallback, an `Option | null` / `Option | string` / `?Option` type,
  a hand-rolled `some()`/`none()` branch, or an `orElse(fn () => Option::some($x))`.
  → backs `NoNullCoalesceToNull`, `NoOptionInUnion`, `PreferOptionFactory`,
  `NoRedundantOrElseWrap`.
- A method typed `: Option` that **never returns `none()`** — Option-as-ceremony.
  → backs `NoOptionOveruse`.

If you are choosing between Option, a plain nullable, a Null Object, or a throw,
read `reference/choosing.md` first.

## What to read when

| Read this | When you are… |
|---|---|
| `reference/api.md` | Writing Option code and need the core API — `getOr`/`getOrThrow`/`transform`/`filter`/`tap`/`andThen`/`orElse`, the static factories (`make`/`find`/`first`/`coalesce`/`someWhen`/`when`), and which combinator replaces which guard. |
| `reference/smells.md` | Fixing or reviewing an Option finding — the bad→good pairs for `getOr(null)`, `?? null`, Option-in-union, unwrap-with-guard, transform-then-getOr(none), redundant orElse wrap, and Option-as-ceremony. |
| `reference/choosing.md` | Deciding whether absence should be Option at all — Option vs plain nullable vs Null Object vs throw, and when Option is overuse. |

## Backs (prophet family)

`NoOptionToNull`, `NoNullCoalesceToNull`, `NoOptionInUnion`, `NoOptionOveruse`,
`UnwrapOptionWithGuard`, `PreferOptionChainOverGuard`, `PreferOptionOverNull`,
`PreferOptionFactory`, `PreferAndThen`, `NoRedundantOrElseWrap`.

A finding from any of these points back here — this skill is the positive
"how to do it right" mirror of what they flag. For the terse always-on rule of
one prophet, run `commandments:scripture --prophet=<NAME>`.
