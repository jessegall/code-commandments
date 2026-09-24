---
name: commandments-csharp-tell-dont-ask
description: "Behaviour belongs with the data it works on. Read this BEFORE you write a C# method that loops over another object's collection or walks its parts to work out something that object could answer, and before a `switch` with type patterns (`case Circle c:`) or an `if (x is A a) … else if (x is B b) …` ladder that asks what a value IS to decide what to do with it — the answer is a member of the type (`order.Total()`, `shape.Area()`)."
---

# C# tell, don't ask — behaviour lives with its data

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Don't ask an object for its insides and then decide for it; tell it what you want and let it answer. A
> method that reaches into one object's collection, or asks what type a value is before acting, is behaviour
> that has been moved away from the class that owns the data.

## The principle

### Working it out from outside

```csharp
public static decimal Total(Order order) => order.Lines.Sum(line => line.Price * line.Quantity);
```

This method knows how an order is built — it has lines, a line has a price and a quantity — and works the
total out from outside. Every caller that needs it either calls this helper or writes the loop again. The
knowledge belongs on the order:

```csharp
public sealed class Order
{
    public decimal Total() => Lines.Sum(line => line.Subtotal);
}
```

Now `order.Total()` is asked, not computed at the caller, and a change to how an order is built changes one
class.

### The type switch

```csharp
var area = shape switch
{
    Circle c => Math.PI * c.Radius * c.Radius,
    Square s => s.Side * s.Side,
    _ => throw new UnknownShape(shape),
};
```

asks each value what it IS so the caller can decide what to do. Every new shape means finding every switch.
Tell instead: give the base type an abstract member — `shape.Area()` — and let each type answer for itself.

### What is NOT this sin

- **A policy over flat fields.** A pricing strategy that reads a customer's tier and a basket's weight is a
  rule *about* the data, not the data's own behaviour; keeping it separate is a design choice.
- **Reading one property.** `order.Id` read to log it is not this; the sin is working out something the
  object could answer.
- **Parsing at the edge.** A type check that turns loose input (`JsonElement`, `object`) into your own types
  is where those types are made, not a switch over them.
- **A closed set you don't own.** Switching over types from a library you cannot change is the only place
  the per-type behaviour can live.

## Rules

- [ ] Move the behaviour onto the object whose data it works on; ask it (`order.HeaviestLine()`), don't reach through it.
      _Move the method onto the envied type and call it there; keep only the orchestration here._
- [ ] Give the base type a member each type answers, and call it; don't switch on which type a value is.
      _Declare `public abstract double Area();` on `Shape`, implement it on `Circle` and `Square`, and write `shape.Area()`._

## Worked example

### csharp-feature-envy

a method that loops another object's collection or writes its members, reaching into it more than into its own state — behaviour exiled from the object it works on

```cs
----------[ Bad ]----------

public int Weigh(Tote tote)
{
    var grams = 0;

    foreach (var item in tote.Items)
    {
        grams += item.Grams;
    }

    return grams;
}

----------[ Good ]----------

public int TotalGrams() => Items.Sum(item => item.Grams);
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=csharp/tell-dont-ask` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-feature-envy`, `csharp-type-switch`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/tell-dont-ask`](../../backend/tell-dont-ask/SKILL.md) — the same discipline in PHP.
- [`csharp/enums`](../enums/SKILL.md) — a closed set of values carries its per-case knowledge beside the `enum`.
- [`csharp/value-objects`](../value-objects/SKILL.md) — the type the behaviour moves onto.
