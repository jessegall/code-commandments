---
name: commandments-resolvers
description: How to do first-match dispatch right with the Resolver + Predicate kernel — read before writing or reviewing any if/match-true chain that maps one input to one of several outputs, any boolean stitched from 3+ guards, or any class named *Resolver.
---

# Resolver / Predicate / Transform / Strategy chains

## Purpose

First-match dispatch — "take one input, run it past a chain of tests, the first
match produces the output" — belongs in a **composed Resolver of NAMED Predicate
objects**, not an `if`/`match (true)` chain and not a Resolver of inline
closures. Each test becomes a class you name, reuse, and compose; the result of
each test is paired with a factory via `->then(...)`.

The kernel is scaffolded under `{{ namespace }}\Resolvers`: a composable
`Resolver`, a `ResolveStrategy` (`FirstResultWins`, `CollectResults`), a
`Predicate` kernel (`IsNull`, `IsEnum`, `HasClass`, `HasPrefix`, `Equals`,
`AllOf`/`AnyOf`/`Negated`) under `Resolvers\Predicates`, `Transform`s under
`Resolvers\Transforms`, and a `ResolverDecorator` base for domain resolvers. Run
`scaffold` if those classes are missing.

## When to use this skill

Pull this skill BEFORE you:

- write a method that is a chain of `if (test) return X;` guards (3+) producing
  one type — that is a resolver in disguise;
- write a `match (true) { test => ..., ... }` that dispatches one value to one of
  several outputs;
- stitch a `bool` method out of 3+ guard conditions — that is a composite
  Predicate, not a resolver;
- compose a `Resolver::firstResultWins(...)` / `::collect(...)` / `::using(...)`
  and are about to pass it `fn (...) => test ? ... : null` closures;
- name a class `*Resolver`, or are reviewing one.

It pairs with the **Resolver prophet family** (`ResolverPattern`,
`ResolverNamingHonesty`) — when one of those fires on your code, this skill is
the "how to do it right" answer.

## What to read when

| Read | When you are |
|---|---|
| `reference/pattern.md` | Turning an `if`/`match (true)` dispatch chain into a composed `Resolver`, or a 3+-guard `bool` into a composite Predicate. Extracting a Resolver's inline closures into named Predicates + factories. Naming a `*Resolver` honestly. |
| `reference/composition.md` | Reaching past the kernel: `->transform()` to pre-process the matched input, `->then()` factories (first-class callables vs. named invokable factory classes), `Resolver::collect`/`using` strategies, and the `ResolverDecorator` base for domain methods (vs. kernel passthroughs). |

## Backs (prophet family)

`ResolverPattern` (nominate dispatch chains; police composed resolvers; SIN a
resolver of 3+ inline closures) and `ResolverNamingHonesty` (a `*Resolver` that
does no dispatch is misnamed). A finding from either points back to this skill.
Read the enforcing rule in full with
`commandments:scripture --prophet=ResolverPattern` (or
`--prophet=ResolverNamingHonesty`).
