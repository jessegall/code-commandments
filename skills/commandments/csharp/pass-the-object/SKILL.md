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

- [ ] Pass the object once and let the method read what it needs from it; a value the method can derive from an argument it already gets is one it should derive itself.
      _Drop the projected parameter and read it inside (`Persist(request)` reading `request.ChannelId`), or take the object in place of its pieces._
- [ ] Take the object the method works on, not an id plus the container it lives in; the caller resolves it once and owns the not-found failure.
      _Change the signature to take the resolved object (`Rename(Node node, string title)`) and resolve at the caller, where the id was born._

## Worked example

### csharp-derived-argument

a call that hands over an object and a projection of it — `Persist(request, request.ChannelId)` — or an object in three pieces, where the method could read them itself

```cs
----------[ Bad ]----------

public sealed class Mover(MoveLog log)
{
    public void Move(StockMove move) => log.Record(move, move.Sku);

    public void Undo(StockMove move)
    {
        var back = move with { FromBin = move.ToBin, ToBin = move.FromBin };
        log.Record(back, back.Sku);
    }
}

----------[ Good ]----------

public sealed class MoveLog
{
    private readonly List<string> lines = [];

    public void Record(StockMove move, string sku) => lines.Add($"{sku}: {move.Quantity} {move.FromBin}->{move.ToBin}");

    public void RecordMove(StockMove move) => lines.Add($"{move.Sku}: {move.Quantity} {move.FromBin}->{move.ToBin}");
}
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=csharp/pass-the-object` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-derived-argument`, `csharp-param-resolved-from-param`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/pass-the-object`](../../php/pass-the-object/SKILL.md) — the same discipline over PHP methods.
- [`csharp/tell-dont-ask`](../tell-dont-ask/SKILL.md) — the sibling: once you hold the object, ask it rather than reaching into it.
- [`csharp/value-objects`](../value-objects/SKILL.md) — the type an id stands in for.
