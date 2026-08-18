---
name: commandments-backend-guard-clauses-and-flow
description: "How a method body is shaped — validate preconditions at the TOP with early return/throw, keep the body flat (no if/elseif/else ladders, no deep nesting), and run the happy path last. NEVER bury a check inline (`($x ?? throw …)->y()`) or in a nested branch. Read this BEFORE writing a method body, a precondition/null check, an `if`, or anything that throws or returns early."
---

# Guard clauses & flow — check at the top, then go straight

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat and
> unindented. A method should read top-to-bottom: *here's what would stop us → here's the work.*

## The principle

Every precondition a method depends on — a value that must be present, a state that must hold — is checked
**at the top** and short-circuits with a `return` or a `throw`. By the time control reaches the real work,
everything it needs is guaranteed, so the work runs at the base indentation level with no `else` and no
nesting. The shape itself documents the contract.

The opposite — burying a check inside an expression, or wrapping the happy path in `if (ok) { … }` — hides
the contract and pushes the body rightward until it's unreadable.

### When to use this skill

Reach for this the moment you are about to write:

- a **precondition / null / state check** at the start of a method;
- an `if` that decides whether the rest of the method runs;
- an `if/elseif/else` chain, or a branch nested two-deep;
- anything that **throws or returns early**.

## Rules

- [ ] State an absent collection at the top as a guard (early return); don't bury `?? []` in a `foreach` header.
      _An early `return` when the collection is absent, so the loop iterates something that is THERE._
- [ ] Flatten with guard clauses — never nest `if`s three deep into a pyramid.
- [ ] Replace a 4+ branch if/elseif ladder with a `match`, a method on the type, or polymorphic dispatch.
      _A `match`, a method on the type, or polymorphic dispatch._
- [ ] Guard at the top with an early `throw`; don't bury a `?? throw` mid-expression feeding further work.
- [ ] Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`.
- [ ] Unfold a nested/chained ternary into a `match` or guards; don't hide branching in `$a ? $b : ($c ? $d : $e)`.
      _A `match`, or early-return guards._
- [ ] Keep `for` for a counted loop, whose step advances a counter; walk with a `while`, or let the type hand out its own sequence.
      _A `while` over an explicit cursor — or better, an iterator on the type being walked, so the caller never holds the cursor at all._
- [ ] Drop the `else` after an `if` branch that already returns/throws/continues/breaks.
- [ ] Branch with an `if`; never run work off the right side of a bare `&&`/`||` statement whose result nothing reads.
- [ ] Choose an action with `if`/`else`; a ternary chooses a VALUE, so never write one whose result nothing reads.

## Worked example

### coalesced-loop-subject

`foreach ($x[$k] ?? [] as …)` — the absence check buried in the loop header instead of stated as a guard

```php
----------[ Bad ]----------

public function fanOut(string $carrier, array $manifest): void
{
    foreach ($manifest[$carrier] ?? [] as $parcel) {
        $this->queued[$carrier][] = $parcel;
    }
}

----------[ Good ]----------

public function fanOutGuarded(string $carrier, array $manifest): void
{
    if (! isset($manifest[$carrier])) {
        return;
    }

    foreach ($manifest[$carrier] as $parcel) {
        $this->queued[$carrier][] = $parcel;
    }
}
```

The other 9 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/guard-clauses-and-flow` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `coalesced-loop-subject`, `deep-nesting`, `if-else-ladder`, `inline-throw`, `loop-inverted-guard`, `nested-ternary`, `non-counting-for`, `redundant-else`, `short-circuit-statement`, `ternary-statement`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `loop-inverted-guard`, `nested-ternary`, `redundant-else`, `short-circuit-statement`, `ternary-statement`. Review it with `--dry-run` first.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 10 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/exceptions`](../exceptions/SKILL.md) — *how* a guard throws (named factory, never a message string).
- [`backend/absence`](../absence/SKILL.md) — *whether* a missing value is a guard-and-throw at all, vs Option / empty / default.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — if every caller re-guards the same value, the guard belongs upstream where the value is born.
