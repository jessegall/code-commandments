---
name: commandments-registry
description: How to build a registry right — register into a keyed store, then look up by a typed contract (`has()` → bool, `get()` → the item or THROWS; never `Option`/`?T`). Read before writing or reviewing a class you `register`/`add`/`put` into, any `*Registry` getter, or a class extending the scaffolded `Registry` base.
---

# Registry — register, then look up by a typed contract

## Purpose

A registry is a class you PUT things into and LOOK things up from over a known
keyspace ("give me the pipeline for this class"). It owns a keyed store and
answers a fixed contract: `has()` → bool, `get()` → the item or **throws** (a miss
is a wiring bug). It NEVER hands back an `Option`/`null` for callers to unwrap — ask
`has()`, then `get()`. The job is to extend ONE shared base so that contract lives —
and is enforced — in one place, instead of hand-rolling and drifting it across every
registry.

## When to use this skill

Pull this skill when you are about to write or review any of:

- a class you `register()` / `add()` / `put()` into that owns a keyed store and
  answers lookups — name it `*Registry` and extend the scaffolded base
  (`{{ namespace }}\Registry`); → read `reference/contract.md` and
  `reference/base-class.md`;
- a public getter on a `*Registry` (or `#[Registry]` / `Registry`-interface)
  class — decide return-or-throw vs finder; an `Option<T>` / `?T` getter on a
  marked registry is a leak; → read `reference/contract.md`;
- a class that has the register-and-look-up **shape** but a name that hides it
  (a "service"/"resolver" you actually `put` into) — name it honestly so the
  contract is legible and enforceable; → read `reference/naming.md`;
- a subclass of the `Registry` base that overrides `all()` to read its OWN store
  without `parent::all()` — that severs the inherited `register()`/`registerMany()`
  (they write a store nothing reads); → read `reference/base-class.md`.

This is the positive twin of the **registry prophet family** below — when one of
them fires, it points back here.

## What to read when

| Read | When you are dealing with |
|---|---|
| `reference/contract.md` | The return contract: `has()` → `bool`, `get()` → `T` or throw; why a registry NEVER returns `Option<T>` (always the sin, any name) and a `?T` getter is a leak unless it is a named NULLABLE finder (`find*`/`try*`/`*OrNull`/`keyForClass`) where absence is a genuine handled outcome. |
| `reference/base-class.md` | Extending the scaffolded `{{ namespace }}\Registry` base instead of hand-rolling register/has/get — and the bypass trap: overriding `all()` to read your own store without `parent::all()` leaves the inherited mutators dead. |
| `reference/naming.md` | Naming honesty: when a register-and-look-up class earns `*Registry` (vs `*Map`/`*Catalog` for a discovered store, `*Resolver`/`*Factory` for compute-on-demand), and why the marker is the opt-in to strict enforcement. |

## Backs (prophet family)

Enforced by **RegistryReturnContract** (a marked registry's getter must return
`T` or throw, not `Option`/`?T`), **RegistryPattern** (2+ hand-rolled registries
with no shared base → extract one — `commandments:scaffold` generates it),
**RegistryNamingHonesty** (a register-and-look-up class whose name hides the
contract), and **RegistryBaseBypass** (a subclass that overrides `all()` past the
base store, killing the inherited `register()`). A finding from any of them
points back to this skill. For the exact rule text run
`commandments:scripture --prophet=<NAME>` (e.g.
`--prophet=RegistryReturnContract`).
