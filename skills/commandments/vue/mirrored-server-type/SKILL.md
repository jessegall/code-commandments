---
name: commandments-vue-mirrored-server-type
description: "You are about to hand-write a TypeScript `interface`/`type` whose fields match a backend `Data` class — a `UserData`, an `OrderData`, the shape an endpoint returns. Read this BEFORE typing out fields that already exist on the server. If the backend owns the shape, the frontend must GENERATE its type from it, not re-declare it."
---

# One source of truth for a server contract — generate the type, don't hand-copy it

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> When a backend `Data` class and a frontend type describe the same shape, only the server's is written by hand.
> The frontend type is generated from it.
> Read this before you write a TypeScript `type` or `interface` for data that comes from the server, and when a
> mirrored-server-type finding points here.
> An arrow in an example (`←`) marks the line the lesson is about.

## The principle

**The server owns the shape. Generate the frontend type; never keep a copy by hand.**

A hand-written copy of a server contract is right on the day it is written. After that, a field is added or
renamed on the server, the copy stays as it was, and nothing complains until a page shows `undefined`.

`spatie/laravel-typescript-transformer` removes the copy: mark the `Data` class `#[TypeScript]`, run the generator
(`php artisan typescript:transform`), and import the generated type. From then on a change to the class is a change
to the type, and the compiler finds every place that must follow.

How to tell a copy from a coincidence: the name and the set of fields line up with a server class (spelling aside:
`first_name` on one side and `firstName` on the other is the same field). A view-model that exists only in the
frontend, with no server class behind it, is not this sin: it has one source, itself.

## The sins

### mirrored-server-type

A hand-written TypeScript type that mirrors a backend `Data` class field for field.

```ts
// Bad: resources/js/types/order.ts
export interface OrderData {      // ← a copy of App\Data\OrderData, kept by hand
  number: string
  total: number
  status: 'pending' | 'paid' | 'shipped'
}

// Good: the type is generated, and imported
import type { OrderData } from '@/types/generated'
```

```php
// The one place the shape is written
#[TypeScript]
final class OrderData extends Data
{
    public function __construct(
        public readonly string $number,
        public readonly int $total,
        public readonly OrderStatus $status,
    ) {}
}
```

## Commands

- `{{binary}} judge --skill=vue/mirrored-server-type` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `mirrored-server-type`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
