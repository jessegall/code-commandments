---
name: commandments-set
description: How to build a set right — an unkeyed, iterate-only collection you `add()` into and read in bulk (`has()` → bool, `all()`/`values()` total; never a keyed `get(string)` and never `Option`/`?T`). Read before writing or reviewing a class you `add`/append into and only iterate, any `*Set` method, or a class extending the scaffolded `Set` base.
---

# Set — add, test membership, iterate (no keys)

## Purpose

A set is an unkeyed, iterate-only collection: you `add()` items, ask `has(item)`,
and read the whole thing in bulk (`all()`/`values()`/iterate). It is the sibling
of a Registry — but a Registry answers *"the value FOR this key"* (a keyed
lookup), while a Set answers *"is this IN, and what is in it"* (membership +
iteration). It owns a collection and is TOTAL over what it holds: there is no
"missing value" branch and no key to look a value up by. The job is to extend ONE
shared base so that contract lives — and is enforced — in one place.

## When to use this skill

Pull this skill when you are about to write or review any of:

- a class you `add()` / append into that you only ever ITERATE (`all`/`values`/
  `foreach`) and test membership on (`has`) — name it `*Set` and extend the
  scaffolded base (`{{ namespace }}\Set`); → read `reference/membership.md` and
  `reference/base-class.md`;
- a method on a `*Set` (or `#[Set]` / `Set`-interface) class that returns a value
  BY KEY (`get(string): T`) — that is a registry's job, not a set's; → read
  `reference/membership.md`;
- a class that has the add-and-iterate **shape** but a vaguer name (a "service"/
  "bag" you actually `add` to and loop over) — name it honestly so the contract is
  legible and enforceable; → read `reference/naming.md`;
- deciding between a Set and a Registry — do callers look entries up by key, or
  only ask membership and iterate? → read `reference/naming.md`.

This is the positive twin of the **set prophet family** below — when one of them
fires, it points back here.

## What to read when

| Read | When you are dealing with |
|---|---|
| `reference/membership.md` | The contract: `has(item)` → `bool`, `all()`/`values()` total, and why a set NEVER returns `Option<T>`/`?T` from its surface and NEVER exposes a keyed `get(string): T` (that means you wanted a Registry). |
| `reference/base-class.md` | Extending the scaffolded `{{ namespace }}\Set` base instead of hand-rolling add/has/all/values. |
| `reference/naming.md` | Set vs Registry: membership + iteration earns `*Set`; a keyed value lookup earns `*Registry`. Why the marker is the opt-in to strict enforcement. |

## Backs (prophet family)

Enforced by **SetNamingHonesty** (an add-and-iterate class whose name hides the
contract → name it `*Set`) and **SetReturnContract** (a marked set must keep a
total surface — `has()` → bool, no `Option`/`?T` leak, and no keyed `get(string)`
lookup). Role-vs-behaviour coherence on a `*Set` (it must not reflect / discover /
do I/O on the side) is enforced by **OutOfPurpose** (see the `registry` skill). A
finding from any of them points back to this skill. For the exact rule text run
`commandments:scripture --prophet=<NAME>` (e.g. `--prophet=SetReturnContract`).
