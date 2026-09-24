---
name: commandments-csharp-type-honesty
description: "Declaring a property or field `T?` that is always set by the time anything reads it, reading it back with `?.` and `??`, silencing the compiler with `!` or `= null!`, filling a required property with `\"\"` or `0` just so the object can be built, saving a field to a local and putting it back later, or a property that returns the same constant however the object was made. Read this BEFORE you make a type nullable to get code to compile, and when a type-honesty finding points here."
---

# C# type honesty — a type must say what is true

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A `T?` that is never actually `null` is a lie that every reader pays for. Each one has to prove
> again that the value is there — `batch?.Permits(sku) ?? false`, `if (batch is null) return;` — and each of
> those fallbacks answers for a state that cannot happen. Make the type say what the design guarantees.

## The principle

### A value that is always there is typed as always there

When a value is always present where it is used, the type says so: a required constructor parameter, a
non-nullable property set in the constructor, a `required` member, a field of a `record`. Declaring it
`T?` "because it is filled in later", or putting it on `this` for the length of one call, moves the
certainty onto every reader, who writes it back with `?.`, `??` or `!`. That defensive code is the
symptom; the cure is upstream, in the type.

### `!` and `= null!` are the same lie, told to the compiler

Nullable reference types let the compiler check absence for you. `value!` and `= null!` switch that check
off at exactly the place it was asked to help. If the value is always there, make the declaration
non-nullable and give it a value where the object is built. If it really can be missing, keep the `?` and
handle the missing case once, where it is decided.

### A required slot is filled with the real value

A `required` property or a non-nullable parameter says "the caller has this". Filling it with `""`, `0`
or `Guid.Empty` to get an object built hides a missing value where no type check can see it. Fetch the
real value, or split off a smaller type that promises only what you actually have.

### State for one call belongs to that call

Saving a field to a local, overwriting it for one operation and putting it back afterwards turns the
object into a scratch pad. The value is per call, so it is a parameter or a local, not a field.

### Not every `null` is a lie

This is the complement of `csharp/absence`. Absence says: when a value really can be missing, say so in
the type and decide what "missing" means where the value is made. Type honesty says: don't invent a
missing case the design does not have.

## Related skills

- [`backend/type-honesty`](../../backend/type-honesty/SKILL.md) — the same discipline in PHP.
- [`csharp/absence`](../absence/SKILL.md) — the complement: absence models a value that really can be missing; this removes one that cannot.
- [`csharp/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is made, not defended at every read.
