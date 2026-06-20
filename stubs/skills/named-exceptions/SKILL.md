---
name: commandments-named-exceptions
description: How to throw failures right — named exception classes with static factories that own their message, never a string at the throw site; also named *Factory methods for non-trivial resolver branches. Read before writing or reviewing any `throw new`, any exception with a message argument, or a `->then(fn () => ...)` branch in a resolver chain.
---

# Named exceptions — static factories, no throw-site strings

## Purpose

A throw site passes DOMAIN VALUES; the exception owns its message. Failures are
named types thrown through static factories (`Thing::notFound($id)`), so they can
be caught by type, carry structured data, and never duplicate a message string
across call sites. The same instinct applies to resolver branches: a non-trivial
`->then()` factory that captures `$this` and does work earns a name on a
`*Factory` class.

## When to use this skill

Reach for this skill when you are about to:

- type `throw new ...` — STOP; never follow `throw new` with a message string.
- write or review any exception that takes a `"..."` message argument.
- hand a multi-word string to an exception factory (`SomeException::make($cls, 'must define ...')`) — that is a leaked message wearing a factory hat.
- write a `->then(fn (...) => ...)` branch in a `Resolver` chain whose body reaches for `$this` and builds something.
- decide where a failure message should live, or how a caller should catch a failure by type rather than by matching a substring.

Pairs with the **named-exceptions prophet family** (below): if one of those
prophets flagged you, this is the "how to do it right" you were pointed at.

## What to read when

| Read this | When |
|---|---|
| `reference/patterns.md` | You are throwing a failure — generic SPL exception, an inline message string, or a factory being used as a message courier. The core named-exception + static-factory pattern, factory naming, and what stays righteous. |
| `reference/branch-factories.md` | You are writing or reviewing a `->then()` branch in a resolver chain and the closure captures `$this` and does real work. Extracting it to a named `*Factory` static method returning `callable`. |

## Backs (prophet family)

This skill is the positive mirror of these enforcing prophets:

- **PreferNamedExceptions** — throw sites that assemble the exception (generic SPL types, inline message strings, factories handed a message string).
- **PreferNamedBranchFactory** — non-trivial `->then()` branch factories that capture `$this` and do work.

A finding from either prophet points back here. For the terse always-on rule,
run `commandments:scripture --prophet=PreferNamedExceptions` (or
`--prophet=PreferNamedBranchFactory`).
