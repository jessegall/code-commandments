---
name: commandments-frontend-mirrored-server-type
description: "You are about to hand-write a TypeScript `interface`/`type` whose fields match a backend `Data` class — a `UserData`, an `OrderData`, the shape an endpoint returns. Read this BEFORE typing out fields that already exist on the server. If the backend owns the shape, the frontend must GENERATE its type from it, not re-declare it."
---

# One source of truth for a server contract — generate the type, don't hand-copy it

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A TypeScript type that restates a backend `Data` class is a **second source of
> truth for one contract**. The two can't be kept in sync by discipline — the day a
> field is added, renamed, or retyped on the server, the hand-written twin lies, and
> nothing tells you. The server already knows the shape; let it emit the type.

## The principle

When a backend `Data` class and a frontend `type` describe the SAME shape, exactly one of them may be
authored by hand — and it must be the one that already validates and hydrates the real payload: the server's.
The frontend type is then **derived**, not written.

`spatie/laravel-typescript-transformer` does the deriving. Mark the class `#[TypeScript]`, point the
transformer at an output file, and run the generator (`php artisan typescript:transform`). It emits a
`.d.ts` the whole frontend imports. Delete the hand-written twin and repoint its importers at the generated
type. From then on a change to the `Data` class is a change to the type — the compiler catches the drift the
hand-copy used to hide.

The tell that you have a duplicate, not a coincidence, is a NAME and a FIELD SET that line up (spelling
aside — `first_name` on one side, `firstName` on the other, is still the same field). A purely-frontend
view-model that no server class backs is not this sin: it has one source of truth, itself. The sin is
specifically the COPY of a shape the server already owns.

## Rules

- [ ] Let the server own the shape: mark the `Data` class `#[TypeScript]`, generate the type, and import the generated one. Never hand-maintain a copy of a server contract.

## Worked example

### mirrored-server-type — in TypeScript

A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes

```ts
----------[ Bad ]----------

export interface OrderData {
  id: string
  total: number
  placedAt: string
  status: string
}

----------[ Good ]----------

export type { OrderData } from '@/types/generated'
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=frontend/mirrored-server-type` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `mirrored-server-type`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
