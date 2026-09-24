---
name: commandments-csharp-role-vocabulary
description: "Writing a C# class that holds a `Dictionary` of things by key and hands them out, one that collects things to ask whether it holds one, or a chain of `if`s that picks the first handler that matches — or naming a class `*Registry`, `*Set` or `*Resolver`. Read this before you write `public Handler? Get(string key)` on a store, and when a role-vocabulary finding points here."
---

# C# role vocabulary — a Registry, a Set, a Resolver, and the contract each name promises

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Three shapes recur everywhere: a keyed store, a membership set, a first-match dispatcher. Each has a name
> and a contract. Name the class for the role and keep the contract — a `*Registry` whose `Get` returns `null`,
> or a `*Resolver` that does not dispatch, is a lie every caller pays for.

## The principle

The relationship runs **both ways**:

- **Shape → name.** A class hand-rolling one of these shapes — a dictionary plus `Register` plus a lookup, an
  add-and-ask collection, an `if` chain returning the first handler that matches — is named for the role.
- **Name → shape.** A class *named* `*Registry`, `*Set` or `*Resolver` behaves like one. The suffix is a
  promise about the contract; breaking it misleads every reader.

And one rule across all three: **a role class does one job.** A registry that also resolves, queries or
assembles is hosting a second engine — move it out.

### Registry — a keyed store

```csharp
public sealed class HandlerRegistry
{
    private readonly Dictionary<string, IHandler> handlers = new();

    public void Register(string kind, IHandler handler) => handlers[kind] = handler;

    public IHandler Get(string kind) =>
        handlers.TryGetValue(kind, out var handler) ? handler : throw UnknownHandler.For(kind);

    public bool Contains(string kind) => handlers.ContainsKey(kind);
}
```

- **`Get` returns the item or throws** — never `IHandler?`. A miss on a registry is a broken invariant
  (nothing registered what the code relies on), not a value for every caller to branch on. Where a miss is
  genuinely expected, ask `Contains` first, or offer the `TryGet(kind, out var handler)` pattern beside `Get`.
- **It stays a store** — no resolving, querying or building inside it.

### Set — membership, unkeyed

`Add(item)`, `Contains(item)`, enumeration. Nothing is looked up by key: if you want an item *by key*, you
wanted a Registry.

### Resolver — first-match dispatch

A sequence of `(predicate, handler)` pairs walked in order, the first predicate that matches deciding the
answer — not an `if`/`else if` ladder re-testing one value, and not a dictionary lookup dressed up as one
(that is a Registry). A class named `*Resolver` that does not dispatch is renamed.

### Classify by type, not a name list

When a role decides "is this one of mine?", it asks the type — an interface, a base class, an `is` test
against something the code declares — never a hardcoded list of type names that rots as types are added
and renamed.

## Related skills

- [`backend/role-vocabulary`](../../backend/role-vocabulary/SKILL.md) — the same roles in PHP, with scaffolded bases.
- [`csharp/absence`](../absence/SKILL.md) — a registry `Get` throws on a miss — the same "a must-exist thing that is missing throws" rule.
- [`csharp/exceptions`](../exceptions/SKILL.md) — the named exception a registry throws on a miss, built by a static factory.
