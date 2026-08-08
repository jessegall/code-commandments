---
name: commandments-typescript-absence
description: "Modelling a value that might not be there in TypeScript — a `?? default`, an `?.` chain, a `field?: T`, a `=== null` or `=== undefined` test. Read this BEFORE writing any of them in a .ts module or a component's script. TypeScript has TWO ways to be missing where PHP has one, and a type that admits absence the design never has is a lie the compiler will not catch."
---

# TypeScript absence — say what is missing, and mean it

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> TypeScript can say a value is missing in two ways, and the difference matters.
> `null` is an absence someone WROTE; `undefined` is an absence that simply happened —
> a property never set, an argument never passed. A codebase that treats them as
> interchangeable has no idea which it is looking at, and the `?? default` written to
> cope with either is the moment a real absence stops being handled and starts being
> hidden.

## The principle

### The type is the claim; the defence is the tell

`field?: T` and `T | null` are claims that the value can be missing. If every path
writes it before anything reads it, the claim is false — and the `?.` and `??` scattered
downstream exist only to satisfy a compiler about a case the program never has. Delete
the optionality and the defences go with it.

The reverse is the same rule read backwards: where a value REALLY can be missing, the
absence is a case to handle, not a hole to fill. `?? 0`, `?? ''`, `?? []` answer the
compiler and lose the question — was it missing, or was it genuinely zero?

### `null` and `undefined` are not two names for one thing

Pick one to MEAN "missing" and let the other be a bug. A property that is sometimes
`null` and sometimes `undefined` forces every reader to test for both, and a test for
one is a silent hole for the other. The two are distinguishable at the type level, so
a codebase that uses both interchangeably has thrown that away for nothing.

### What TypeScript does NOT get from the backend

There is no `Option` here, and no Null Object worth the ceremony for a plain data
shape. The tools are the type itself (`T` vs `T | null`), a narrowing guard at the top
of the function, and a total value the caller can always use. That is the whole kit —
which is why this is its own skill rather than a translation of the PHP one.

## Rules

- Reach for `?.` only where the type admits absence; on a field declared total it is noise that teaches the next reader to doubt it.
  _A plain `.` — the declaration already guarantees it._
- Do not declare a field optional when it always has a value: drop the `?` and the `| null`, and the defences downstream go with them.
  _Declare it as its plain type — the initialiser already proves it is total._

## Bad → good

### defended-certain-field

An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, which reads as doubt the design does not have

```ts
----------[ Bad ]----------

return this.customer?.name

----------[ Good ]----------

return this.shipment?.trackingCode ?? 'pending'
```

### falsely-optional-field

A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen

```ts
----------[ Bad ]----------

private items?: Item[] = []

----------[ Good ]----------

private coupon?: Coupon
```

## When it fires

- An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, which reads as doubt the design does not have — `DefendedCertainFieldDetector`
- A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen — `FalselyOptionalFieldDetector`

## Checklist

- [ ] Reach for `?.` only where the type admits absence; on a field declared total it is noise that teaches the next reader to doubt it.
- [ ] Do not declare a field optional when it always has a value: drop the `?` and the `| null`, and the defences downstream go with them.

## Related skills

- [`backend/absence`](../../backend/absence/SKILL.md) — the same instinct on the server, with the tools PHP has and TypeScript does not.
- [`backend/type-honesty`](../../backend/type-honesty/SKILL.md) — the general rule this serves: a type must not claim an optionality the design doesn't have.
