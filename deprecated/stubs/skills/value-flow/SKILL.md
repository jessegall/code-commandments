---
name: commandments-value-flow
description: How to reason about where a value comes from and where it goes ACROSS artifacts (config files, migrations, enums, call sites) instead of judging one node in isolation. Read before hardcoding a set that config already declares, reading a config()/migration-backed value, dispatching on a string that mirrors an enum, or threading a dependency through a class that never uses it.
---

# Value flow — trace a value across artifacts, not one node at a time

## Purpose

Most rules judge a single expression. This family judges a value's *journey*: the
same closed set declared in two places, a `config()` key that does not exist, a model
attribute that disagrees with its column, a dependency that only passes through. The
bug is never in one line — it is in the **drift between two artifacts** (code ↔
config, model ↔ migration, match ↔ enum) or in a value that is **produced but never
consumed**. Fix the flow, and a whole class of silent-null / wrong-type / dead-code
bugs disappears.

## When to use this skill

Pull this skill when you are about to write or review any of:

- an **enum / match / `if` chain whose member set is ALSO declared as data in a
  config file** — that is a config-driven registry hiding as code; the per-member
  wiring (a case, a match arm, a method) is the duplication a registry removes.
  `PreferConfigDrivenRegistry` / `StringMatchMirrorsEnum`;
- a **`config('a.b.c')` read** — the key must exist in the config tree, or it returns
  `null` silently. `ConfigKeyContract`;
- an **Eloquent model** with `$fillable`/`casts()` — a `json`/`bool`/`datetime`/
  `decimal` column its migration declares must be cast, or you read a raw string.
  `MigrationModelDrift`;
- a **constructor-injected dependency** — if the class only forwards it to one
  collaborator and never calls it, inject it at the collaborator. `PassThroughDependency`;
- a **private method that returns a value** — if every caller discards the result,
  make it `void` (or use the result). `DeadProducer`.

## The core move — congruence and reachability

- **Cross-artifact congruence** (`PreferConfigDrivenRegistry`, `ConfigKeyContract`,
  `StringMatchMirrorsEnum`, `MigrationModelDrift`): the same set/key/type is declared
  in two artifacts; when they match exactly, code is duplicating data — collapse to
  one source. When they DON'T match (a key absent from the tree, a column with no
  cast), one side is silently wrong.
- **Forward consumption** (`DeadProducer`): follow a produced value to its sinks; if
  every sink discards it, the production is dead.
- **Backward / in-place origin** (`PassThroughDependency`): follow an injected value;
  if it only ever flows straight back out to one collaborator, the hop is needless.

The discipline: act only when the flow is **fully resolvable and unambiguous** — a
dynamic key, an unknown table, an unseen caller, a custom base → leave it. A
value-flow finding you can't prove is noise.

## How to fix each shape

| Finding | Fix |
|---|---|
| set mirrors a config map | register the configured members into a registry in a provider; drop the per-member code |
| `config('x.y')` key undeclared | fix the typo to the declared sibling, or add the key to the config file |
| match over strings == enum values | type the subject as the enum (`Enum::from($s)`) and match its cases |
| migration column not cast | add the cast: `casts(): array { return ['meta' => 'array', 'paid' => 'boolean']; }` |
| dependency only forwarded | inject it at the collaborator; drop the relay parameter |
| private producer always discarded | return `void` and drop the `return` (keep the side effect), or use the result |
| translation key absent from the lang tree | fix the key to the declared sibling, or add it to the lang file |
| `config()`/env value strict-compared to a number | the value is a string at the boundary — cast it, or compare loosely/with the typed config |
| values that always travel together | introduce a value object that carries the clump as one typed argument |
| hardcoded literal that is really config | move it to the config file and read it via `config()` |
| request input reaching raw SQL / `exec` | bind/escape at the sink, or route through a validated typed DTO — never concatenate request input into a sink |
| secret reaching a log or response | redact it; never log or return a credential/token |

## Backs (prophet family)

Enforced by **PreferConfigDrivenRegistry**, **ConfigKeyContract**,
**TranslationKeyCongruence**, **MixedConfigValueUsedTyped**,
**HardcodedLiteralShouldBeConfig**, **StringMatchMirrorsEnum**,
**MigrationModelDrift**, **TaintedInputToSink**, **SecretToLogOrResponse**,
**DataClumpToValueObject**, **PassThroughDependency**, **DeadProducer** — all
advisory (warnings: human-reviewed). When one fires, it points back here.

Read any rule in full: `commandments scripture --prophet=<Name>`.
