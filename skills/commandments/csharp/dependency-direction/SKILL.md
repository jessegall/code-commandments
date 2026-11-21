---
name: commandments-csharp-dependency-direction
description: "Using a type from another of the project's own namespaces. Read this before `Shop.Domain` code reaches for a type in `Shop.Web`, before a new `using` between two of your namespaces, and when a namespace-cycle or namespace-dependency finding points here. A declared layer may only use the layers it said it may use."
---

# C# dependency direction — references point down the stack

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A namespace is a claim about what depends on what. One reference going the wrong way makes the two
>         namespaces really just one namespace split across two names, so the direction is declared once and every
>         reference is checked against it.

## The principle

`Shop.Domain` says "this is the business, and it knows nothing about how it is shown"; `Shop.Web` says "this
is built on top of the domain". The claim is worth exactly as much as the code: one `OrderPage` used inside
`Shop.Domain` and the two can no longer be understood, tested or reused apart.

Nothing about the arrow shows when you write it. The project compiles and the tests pass. When both
namespaces live in one project, the compiler never stops a reference going the wrong way.

### Declare the stack

The direction is declared once, in the project's `.commandments/config.json`, each layer naming the layers
it may use — here the domain uses only itself, the application its domain, and the web layer both:

```json
"configure": {
    "csharp/NamespaceDependencyDetector": [
        {"layer": ["Shop.Domain"]},
        {"layer": ["Shop.Application", ["Shop.Domain"]]},
        {"layer": ["Shop.Web", ["Shop.Application", "Shop.Domain"]]}
    ]
}
```

### What is judged

- **Every reference the compiler resolved**, not only the `using` lines: a type in a declaration, the type
  of a value, the target of a call, and the type arguments inside any of them. A fully qualified name counts
  the same as one brought in by a `using`.
- **Only the project's own types.** A framework or package type is always allowed.
- **Only from a declared layer.** A layer contains its own nested namespaces, so references within a layer
  are fine.
- **Cycles, declared or not.** Two of the project's namespaces that each use the other are one namespace,
  whatever the folders say.

### First: which side is wrong?

A layer finding measures the code against a declaration someone wrote, and a declaration can be stale. Before
changing either, ask whether the declared stack still describes the design this project wants. Usually the
reference is the accident; when you conclude the declaration is wrong, say so to the user with your
reasoning. Never quietly edit the declaration to make a finding go away.

### The fix

Move the thing both sides need down into the lower layer, pass it in from above, or invert the dependency
behind an interface the lower layer owns and the upper layer implements.

## Rules

- [ ] Declare the dependency in the constructor.
      _Take the service directly and leave lookup to the composition root._
- [ ] Keep references between the project's namespaces pointing one way; two namespaces that use each other are a cycle.
      _Cut the thinner direction: move what both need into the lower namespace, pass it in from above, or invert it behind an interface the lower one owns._
- [ ] A declared layer may only use the namespaces it declared in its `mayUse` — down the stack, never back up or sideways.
      _Move what both need down into the lower layer, pass it in from above, or invert it behind an interface the lower layer owns — and if the declaration is what is wrong, say so rather than editing it quietly._
- [ ] Remove registrations for services no production code uses.
      _Remove the unused registration._

## Worked example

### csharp-container-reach

A class locates a service through IServiceProvider

```cs
----------[ Bad ]----------

public sealed class ParcelSnapshotReader(IServiceProvider source, Action<string> intake)
{
    private readonly IServiceProvider intakeServices = source;

    public bool PublishProjection(string gate)
    {
        if (gate.Length == 0) return false;
        var projection = this.intakeServices.GetRequiredService<ParcelSnapshotProjection>();
        intake(gate + ": " + projection.GetType().Name);
        return true;
    }

    public decimal ReadParcels(DbSet<ParcelSnapshotProjection> parcels, Queue<int> queue, decimal running)
    {
        var total = running;
        while (queue.TryDequeue(out var next))
        {
            var label = parcels.Find(next);
            total += label.Length;
            intake(label);
        }
        return total;
    }

    public string ReadOne(DbSet<ParcelSnapshotProjection> rows, int identifier)
    {
        return rows.Find(identifier);
    }

    public string ProjectionName(ParcelSnapshotProjection projection)
    {
        return projection.GetType().Name;
    }
}

----------[ Good ]----------

public sealed class ParcelSnapshotReader(IServiceProvider source, Action<string> intake)
{
    private readonly IServiceProvider intakeServices = source;

    public bool PublishProjection(string gate)
    {
        if (gate.Length == 0) return false;
        var projection = this.intakeServices.GetRequiredService<ParcelSnapshotProjection>();
        intake(gate + ": " + projection.GetType().Name);
        return true;
    }

    public decimal ReadParcels(DbSet<ParcelSnapshotProjection> parcels, Queue<int> queue, decimal running)
    {
        var total = running;
        while (queue.TryDequeue(out var next))
        {
            var label = parcels.Find(next);
            total += label.Length;
            intake(label);
        }
        return total;
    }

    public string ReadOne(DbSet<ParcelSnapshotProjection> rows, int identifier)
    {
        return rows.Find(identifier);
    }

    public string ProjectionName(ParcelSnapshotProjection projection)
    {
        return projection.GetType().Name;
    }
}
```

The other 3 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=csharp/dependency-direction` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-container-reach`, `csharp-namespace-cycle`, `csharp-namespace-dependency`, `csharp-orphaned-binding`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/dependency-direction`](../../php/dependency-direction/SKILL.md) — the same discipline over PHP namespaces.
- [`csharp/fix-at-the-source`](../fix-at-the-source/SKILL.md) — the arrow that points the wrong way is where the fix belongs.
