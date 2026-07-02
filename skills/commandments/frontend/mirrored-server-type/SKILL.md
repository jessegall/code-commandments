---
name: commandments-frontend-mirrored-server-type
description: You are about to hand-write a TypeScript `interface`/`type` whose fields match a backend `Data` class — a `UserData`, an `OrderData`, the shape an endpoint returns. Read this BEFORE typing out fields that already exist on the server. If the backend owns the shape, the frontend must GENERATE its type from it, not re-declare it.
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

- Let the server own the shape: mark the `Data` class `#[TypeScript]`, generate the type, and import the generated one. Never hand-maintain a copy of a server contract.

## When it fires

- A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes — `MirroredServerTypeDetector`

## Checklist

- [ ] Let the server own the shape: mark the `Data` class `#[TypeScript]`, generate the type, and import the generated one. Never hand-maintain a copy of a server contract.
