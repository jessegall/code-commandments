---
name: commandments-boundary-typing
description: How to type a value honestly at a deserialization / internal seam — read before adding `?? ''` into a required slot, an all-nullable boundary DTO, a `T|array` / `mixed` seam, a `T|false` found-or-not return, or a null-guard on a non-nullable value.
---

# Boundary typing — make the type tell the truth about absence

## Purpose

A type is a contract. `string $id` (non-nullable, no default) promises every
instance has a real id; `?T = null` promises the value can genuinely be absent.
The **BoundaryTyping** discipline (the `TypeHonesty` prophet) flags every place
where a value crosses a deserialization boundary (a `Spatie\Data` / `FormRequest`
DTO) or an internal seam (a private/protected method) **typed as a lie** — punted
as nullable / `mixed` / `T|array` / `T|false` / an empty-literal so downstream code
has to re-coerce it.

The fix is never to silence the type. It is to make the type **honest**: assert
the value that is truly required (fail loud), or declare the value that is truly
optional (let the absence flow). This skill is the "how to make it honest"
playbook — the positive mirror of what the prophet flags. For the terse always-on
rule, run `commandments:scripture --prophet=TypeHonesty`.

## When to use this skill

Pull this skill whenever you are about to write — or are fixing a finding on:

- A `::from([...])` hydration filling a **required, non-nullable `string`** slot
  with `?? ''` / `?? T_String::empty()` — a manufactured empty value. → V1 (sin)
- A boundary DTO whose **every** field is `?T = null` (≥2 fields), where a consumer
  actually treats one of those fields as a required value. → V2 (warn)
- A boundary DTO field typed `?T` that the **same class's `rules()`** marks
  `required` (or carries `#[Required]`). → V5 (sin)
- A private/protected seam typed `T|array` (T a project Data class), or exactly
  `mixed`/`object` where every caller passes one concrete type. → V3 / V4 (warn)
- A `T|false` return encoding found-or-not. → V6 (warn) — model presence with Option.
- A `=== null` / `!== null` / `is_null()` guard on a value the declared type says
  is **non-nullable**. → V7 (warn) — dead guard, the type already excludes null.
- A boundary DTO with a `mixed` payload + a discriminator that a consumer
  `match`-es and re-coerces per arm. → V8 (warn) — an untyped tagged-union.

See `reference/verdicts.md` for the full bad→good per verdict.

## The core decision: assert, declare-optional, or absolve

V1 / V2 / V5 all reduce to ONE judgment about each flagged field — **is this value
truly required at this boundary?** Three honest outcomes, never a fourth (`?? ''`):

| The value is… | Make it honest by… | Example |
|---|---|---|
| **Truly required** — a persisted-row invariant, or required by a base contract | **Assert it** — throw at the boundary so hydration fails loud. Fold into an existing guard if one already covers a sibling field. | `createdAt` of an already-saved row; an action `id` required by its base `AssistantAction` (fold into the guard that already throws for a missing `nodeId`). |
| **Genuinely optional / display-only** | **Make the field honestly nullable** — declare `?T` on the target and let the absence flow. | A `summary` shown on a button card — `AssistantUpdateNodeAction::$summary` should be `?string`. |
| **A tolerant boundary** — accepts alternative shapes, or an untrusted wire frame; **no single field is actually required** | **Absolve** (an audited act, the human's call). Asserting would throw on every legitimate alternative / garbage frame. | `RawAssistantEdge` (canonical-or-flat-or-source/target alternatives); `DisconnectPayload` (from+to **or** edges); a browser `ClientMessage` wire-frame. |

Rule of thumb for the unsure case: **if a required field can really be absent,
make it nullable and let the absence flow downstream; if it cannot, assert it at
the boundary so hydration fails loudly. Never synthesize `''` to satisfy the
type** — that drops the absence signal and manufactures a fake-valid value the
rest of the system trusts.

### Why not the easy fixes

- `?? ''` (or `?? 0` / `?? []` on a string slot) — manufactures a value that
  looks valid and is not. The contract said "required"; you just lied to it.
- Making *everything* nullable — tolerant, but throws away the "this is required"
  contract for the fields that genuinely are, pushing every check downstream.
- Throwing on *every* missing field — too strict for a display-only or
  alternative-shape value; it turns an optional absence into a hard failure.

Judge **per field**, by what the value *is*.

## Not auto-fixable — and why

These findings are deliberately not `[AUTO-FIXABLE]`: choosing between *assert*,
*make-nullable*, and *absolve* is a human judgment about whether the value is
truly required. The tool can prove the type is dishonest; only you know the domain
contract. That's the whole point of a compiler-grade signal — it points exactly at
the mistyped value and leaves the contract decision to you.

## Backs (prophet family)

`TypeHonesty` (the BoundaryTyping discipline — verdicts V1–V8).

A finding from any of its verdicts points back here. As the discipline absorbs the
loose boundary/coalesce prophets (the retirement map in
`docs/discipline-migration-tracker.md`), their guidance folds into this skill — one
discipline, one skill.
