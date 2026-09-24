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

The direction is declared once, in the project's `.commandments/config.php`, each layer naming the layers
it may use:

```php
$config->configure(fn (\JesseGall\CodeCommandments\Detectors\CSharp\NamespaceDependencyDetector $d) => $d
    ->layer('Shop.Domain')                                         // the business: itself only
    ->layer('Shop.Application', mayUse: ['Shop.Domain'])           // use cases over the domain
    ->layer('Shop.Web', mayUse: ['Shop.Application', 'Shop.Domain'])
);
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

- [ ] Keep references between the project's namespaces pointing one way; two namespaces that use each other are a cycle.
      _Cut the thinner direction: move what both need into the lower namespace, pass it in from above, or invert it behind an interface the lower one owns._

## Worked example

### csharp-namespace-cycle

two of the project's namespaces that each use the other — a cycle that makes them one namespace split under two names

```cs
----------[ Bad ]----------

public int Total(Promotion promotion)
{
    switch (promotion)
    {
        case PercentOff percent:
            return subtotal - subtotal * percent.Percent / 100;
        case AmountOff amount:
            return subtotal - Math.Min(amount.Amount, subtotal);
        default:
            return subtotal;
    }
}

----------[ Good ]----------

public bool Covers(Shop.Rewards.Voucher voucher) => balance >= voucher.Cost;
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/dependency-direction` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-namespace-cycle`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/dependency-direction`](../../backend/dependency-direction/SKILL.md) — the same discipline over PHP namespaces.
- [`csharp/fix-at-the-source`](../fix-at-the-source/SKILL.md) — the arrow that points the wrong way is where the fix belongs.
