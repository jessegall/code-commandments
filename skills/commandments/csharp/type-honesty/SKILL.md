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

## Rules

- [ ] A method that works something out gives it back: return the value, and let the caller decide where it goes.
      _Return what the method works out and let the caller assign it, or ask the object to change itself, so the signature says what happens; `out` says it in the signature when the value really must travel that way._
- [ ] A guard answers a question the type leaves open: if the type says the value is there, delete the guard; if the value really can be missing, say so in the type.
      _Delete the check when the type is right. When callers really do pass null, type it `Order?` and let the reader and the compiler see it, then decide absence once where the value is born._
- [ ] A required slot means the caller has the value; fill it with the real value, never with `""`.
      _Fetch the real value, or make the slot `string?` if it really can be missing — or split off a smaller record that only promises what you have._

## Worked example

### csharp-filled-parameter

a method returning nothing that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed

```cs
----------[ Bad ]----------

private void ApplyPlan(DealerRecord record)
{
    record.Plan = plans.TryGetValue(record.DealerId, out var plan) ? plan : "free";
}

----------[ Good ]----------

public sealed class DealerFactsReader(IReadOnlyDictionary<string, string> plans, IReadOnlyDictionary<string, string> languages)
{
    public string Gather(string dealerId)
    {
        return $"{dealerId}:{this.PlanOf(dealerId)}:{this.LanguageOf(dealerId)}";
    }

    private string PlanOf(string dealerId) => plans.TryGetValue(dealerId, out var plan) ? plan : "free";

    private string LanguageOf(string dealerId) => languages.TryGetValue(dealerId, out var language) ? language : "nl";
}
```

The other 2 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=csharp/type-honesty` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-filled-parameter`, `csharp-impossible-guard`, `csharp-placeholder-filled-data`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 3 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/type-honesty`](../../php/type-honesty/SKILL.md) — the same discipline in PHP.
- [`csharp/absence`](../absence/SKILL.md) — the complement: absence models a value that really can be missing; this removes one that cannot.
- [`csharp/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is made, not defended at every read.
