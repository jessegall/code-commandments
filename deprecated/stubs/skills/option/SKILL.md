---
name: commandments-option
description: How to do absence right with Option<T> — read before writing or reviewing any nullable return, `unwrapOr(null)`, `?? null`, Option-in-a-union, an always-some Option, or a wrap-then-unwrap.
---

# Option&lt;T&gt; — present-or-absent without null

## Purpose

`Option<T>` puts a value's presence into the type instead of a null you must
remember to check. A method that can yield nothing returns `Option<T>`, never
`T|null`; the empty case becomes part of the type and impossible to forget.

The canonical Option is `JesseGall\PhpTypes\Option` (require `jessegall/php-types`).
**Unwrapping or branching on an Option is normal** — `unwrap()`, `isNone()`,
`unwrapOr($d)`, a `match` on it. That is how you use one; it is never the smell.
The only smell is a type that *misrepresents* absence: a bare null callers must
juggle, or an Option that is never empty.

## When to use this skill

Pull this skill whenever you are about to write or review:

- A method that **sometimes returns a value and sometimes returns null** — and
  has more than one or two callers that branch on it. Model the absence. →
  backs `OptionDiscipline` (adopt).
- A method typed `: Option` that **never returns `none()`** (every return is
  `Option::some(...)`), or `Option::some($x)->unwrap()` — an Option built and
  unwrapped in one breath. → backs `OptionDiscipline` (overuse).
- `unwrapOr(null)` / `toNullable()` that throws the Option straight back into a
  nullable, or a `?? null` no-op. → backs `NoOptionToNull`, `NoNullCoalesceToNull`.
- An `Option | null` / `Option | string` / `?Option` type. → backs
  `NoOptionInUnion`.

If you are choosing between Option, a plain nullable, a Null Object, or a throw,
read `reference/choosing.md` first.

## What to read when

| Read this | When you are… |
|---|---|
| `reference/api.md` | Writing Option code and need the core API — `some`/`none`/`fromNullable`, `isSome`/`isNone`, `unwrap`/`expect`/`unwrapOr`/`unwrapOrElse`/`toNullable`, `map`/`filter`/`andThen`/`or`/`orElse`. |
| `reference/smells.md` | Fixing or reviewing an Option finding — the bad→good pairs for decides-null, always-some, wrap-then-unwrap, `unwrapOr(null)`, `?? null`, Option-in-union. |
| `reference/choosing.md` | Deciding whether absence should be Option at all — Option vs plain nullable vs empty collection vs Null Object vs throw, and when Option is overuse. |

## Backs (prophet family)

`OptionDiscipline`, `NoOptionToNull`, `NoNullCoalesceToNull`, `NoOptionInUnion`.

A finding from any of these points back here — this skill is the positive
"how to do it right" mirror of what they flag. For the terse always-on rule of
one prophet, run `commandments:scripture --prophet=<NAME>`.
