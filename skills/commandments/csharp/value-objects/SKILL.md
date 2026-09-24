---
name: commandments-csharp-value-objects
description: "Passing related data around in C# as a `Dictionary<string, object>` or `Dictionary<string, string>` read by fixed keys (`row[\"sku\"]`), a `JsonNode`/`JsonElement` read field by field, a tuple returned and destructured, or the same three or four parameters handed from method to method side by side. Read this BEFORE writing any of them."
---

# C# value objects — give related data a type

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Data that travels together is a **thing**, not a loose pile of dictionaries and primitives.
> The moment a cluster of values is passed around, returned, or read by string keys, it wants a name
> and a type — the type IS the documentation, the validation and the contract, checked by the
> compiler instead of by every reader's memory.

## The principle

### A record is a type

When the keys of a dictionary are known in advance — the same handful, read by name — the dictionary
is a record, and a record is a type:

```csharp
public sealed record Line(string Sku, int Quantity, long UnitPriceCents)
{
    public long Total => Quantity * UnitPriceCents;
}
```

The members are declared once, the compiler sees every read, a missing value fails where the object
is built, and a `record` cannot be changed behind your back — `with` makes the changed copy.
Behaviour that reads only those members — a total, a label — becomes a member of it.

### Build it at the edge

Loose data arrives as dictionaries and JSON: a request body, a form, a row. Turn it into the type
**where it enters** — `JsonSerializer.Deserialize<Line>(json)`, or one `Line.From(row)` factory — and
pass the type from there on. The rest of the program never sees the dictionary, so it never has to
wonder which keys are there, or cast what they hold.

### Values that always travel together are one value

Three parameters that every caller passes side by side — `street, city, postcode` — are an `Address`
waiting to be named. Give them one type and pass that; a small one is a `readonly record struct`.
A tuple returned and taken apart by position is the same thing unnamed.

### What is NOT this sin

- A dictionary used as a **mapping**: keys that are data (a SKU → stock level), iterated or looked up
  by a value you did not write in the source.
- The dictionary a serializer or a framework hands you right before you convert it, and options
  forwarded unchanged.

## Rules

- [ ] Return a `record` with those fields, not a dictionary built with fixed string keys.
      _Declare `public sealed record StockLine(string Sku, int Qty);` and return `new StockLine(sku, qty)`._
- [ ] Bundle values that always travel together into one type — a record — instead of threading them side by side.
      _Name the clump as a record (a `readonly record struct` when it is small), build it once where the values meet, and pass that instead of the separate parameters._
- [ ] Give a record a type — an immutable `record` — instead of a dictionary read by string keys.
      _Declare the keys as members of a record, build it where the data enters (`JsonSerializer.Deserialize<T>` or a static `From` factory), and take that type from there on._
- [ ] Build a record complete and never change it; derive a new one with `with` instead.
      _Use `{ get; init; }` instead of `{ get; set; }`, and turn `void Add() { Items++; }` into `Cart Added() => this with { Items = Items + 1 };`._
- [ ] Return a `record` or a tuple with named slots, not a tuple the caller has to read by position.
      _Declare `public sealed record Totals(decimal Net, decimal Vat, string Currency);` and return it — or at least name the slots: `(decimal Net, decimal Vat, string Currency)`._

## Worked example

### csharp-array-return-bag

a method that returns `new Dictionary<string, object> { ["sku"] = …, ["qty"] = … }` — a record with fixed fields, handed back as a dictionary

```cs
----------[ Bad ]----------

public static Dictionary<string, object> Footer(string orderId, int totalCents, DateOnly paidOn) => new()
{
    ["order"] = orderId,
    ["total"] = totalCents,
    ["paid"] = paidOn,
};

----------[ Good ]----------

// in Receipts.cs
public sealed record ReceiptFooter(string OrderId, int TotalCents, DateOnly PaidOn);

// in Receipts.cs
public static ReceiptFooter PaidToday(string orderId, int totalCents) => new(orderId, totalCents, DateOnly.FromDateTime(DateTime.Today));
```

The other 4 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=csharp/value-objects` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-array-return-bag`, `csharp-data-clump`, `csharp-dictionary-bag`, `csharp-mutable-value-object`, `csharp-positional-tuple-return`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 5 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/value-objects`](../../backend/value-objects/SKILL.md) — the same discipline on the PHP backend, with Spatie `Data`.
- [`python/value-objects`](../../python/value-objects/SKILL.md) — the same discipline in Python, with frozen dataclasses.
- [`csharp/absence`](../absence/SKILL.md) — what a member of the new type holds when its value may be missing.
