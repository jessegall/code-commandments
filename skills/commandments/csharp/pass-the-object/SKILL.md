---
name: commandments-csharp-pass-the-object
description: "Writing a C# method that takes an object and an id and whose first move is to look one up in the other — `Rename(Workflow workflow, string nodeId)` doing `workflow.Graph.Node(nodeId)` — or one that takes a value its caller had to convert, derive or compute a bool from first. Read this before adding an `Id` parameter beside the object it keys into, and when a pass-the-object finding points here."
---

# C# pass the object — demand what you use, not an id and its container

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> When a method's first act is to resolve one parameter against another, the signature lies about what the
> method needs. The caller resolves once, where the id was born, and passes the object the method works on.

## The principle

```csharp
public void Rename(Workflow workflow, string nodeId, string title)
{
    var node = workflow.Graph.Node(nodeId);
    node.Title = title;
}
```

The method only ever wanted the node. Taking the workflow and an id means:

- **The caller already had both.** It can resolve the node itself; nothing is gained by putting it off.
- **The not-found failure lands in the wrong place.** Whoever named the id is the one who can say what a
  missing node means; buried inside `Rename`, that handling spreads to every method like it.
- **The type says nothing.** `string nodeId` where a `Node` is meant is primitive obsession.
- **It ties the method to the container's lookup** (`workflow.Graph.Node`) for no reason.

So the signature asks for what it uses — `public void Rename(Node node, string title)` — and the caller
resolves once.

### The same smell, other shapes

- **A converted argument** — every caller passes `order.Id.ToString()` or `(decimal) amount`: the method
  wants the other type; take it, or take the object and convert inside, once.
- **A derived argument** — every caller passes `order.Customer` beside `order`: the method can read it.
- **A computed bool** — every caller passes `isPaid: order.Status == OrderStatus.Paid`: hand over the order
  and let the method ask it.

### What is NOT this sin

- **A registry or repository keyed into its own store** — `handlers[kind]` is the object's job.
- **A boundary** — a controller action, a message handler or a CLI command receives ids from outside; that is
  where they are resolved.
- **A lookup whose contract is "by id"** — `FindById(id)` that hands the result straight back.

## Rules

- [ ] Declare the parameter in the type callers actually hold and convert inside — one rule about the conversion, in one place.
      _Move the conversion into the method and take what the callers had (`ReceiptFor(Order order)` or `ReceiptFor(int orderId)`); a caller that forgets the conversion can no longer pass the wrong thing._

## Worked example

### csharp-converted-argument

a scalar parameter its callers keep filling with the same conversion — `ReceiptFor(order.Id.ToString())` call after call — because it asks for the converted form instead of the value

```cs
----------[ Bad ]----------

// in Barcodes.cs
public string Top(Parcel parcel) => barcodes.Encode(parcel.Id.ToString());

// in Barcodes.cs
public IEnumerable<string> All(IEnumerable<Parcel> parcels) =>
    parcels.Select(parcel => barcodes.Encode(parcel.Id.ToString()));

// in CreditNotes.cs
public static CreditNote For(IReadOnlyList<ReturnedLine> lines)
{
    var note = new CreditNote();

    foreach (var line in lines)
    {
        note.Credit((decimal) line.Refunded);
    }

    return note;
}

// in CreditNotes.cs
public static CreditNote Single(ReturnedLine line)
{
    var note = new CreditNote();
    note.Credit((decimal) line.Refunded);

    return note;
}

// in CreditSummaries.cs
public decimal Credited(IEnumerable<ReturnedLine> lines, CreditNote note)
{
    foreach (var line in lines.Where(line => line.Refunded > 0))
    {
        note.Credit((decimal) line.Refunded);
    }

    return note.Total;
}

// in BinImports.cs
public int Load(IEnumerable<BinRow> rows)
{
    return rows.Count(row => rack.Claim(int.Parse(row.Aisle), int.Parse(row.Level)));
}

// in BinImports.cs
public bool Reload(BinRow row) => rack.Claim(int.Parse(row.Aisle), int.Parse(row.Level));

----------[ Good ]----------

public string Encode(Guid parcelId) => $"*{parcelId.ToString("N").ToUpperInvariant()}*";
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/pass-the-object` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-converted-argument`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/pass-the-object`](../../backend/pass-the-object/SKILL.md) — the same discipline over PHP methods.
- [`csharp/tell-dont-ask`](../tell-dont-ask/SKILL.md) — the sibling: once you hold the object, ask it rather than reaching into it.
- [`csharp/value-objects`](../value-objects/SKILL.md) — the type an id stands in for.
