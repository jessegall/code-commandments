---
name: commandments-backend-type-honesty
description: "A type must tell the truth about the value. Don't fake optionality — a `?T` / nullable that the design always has set, which the code then immediately defends against (`?->`, `?? <fake>`, null-checks) or stashes as mutable scratch state and restores. The defence is the tell that the type is lying. Make the type carry the certainty: pass the value as a parameter, hold it non-nullable, or wrap per-call context in a value object. Read this BEFORE you add a nullable field set later in a method, or reach for `$this->scratch?->… ?? false`."
---

# Type honesty — the type must not lie

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A `?T` that is never actually null is a lie the whole codebase pays for. Every reader has to re-prove the
> value is there — `?->`, `?? <default>`, an `if ($x === null)` — and one of those defaults silently answers
> a question for a state that can't happen. Make the type say what the design guarantees.

## The principle

When a value is **always present where it's used**, the type should say so: a non-nullable field, a
constructor parameter, a method parameter, a value object. Hedging it as a **nullable that's set later** —
or a **mutable field used as per-call scratch** — pushes the certainty back onto every caller, who re-
establishes it with defensive code. The defensive code is the smell; the cure is upstream, in the type.

This is the complement of [`absence`](../absence/SKILL.md): *absence* says model genuine missingness
honestly (Option / empty / throw); *type-honesty* says don't manufacture missingness the design doesn't
have. A value that's truly optional belongs in `absence`. A value that's always there but typed `?T` for
convenience belongs here.

### What is NOT this sin

- **A genuinely optional, constructor-injected collaborator** read with `?->… ?? …`. If `$this->logger` is
  injected once and may legitimately be absent, defaulting it is a Null-Object choice, not a masked
  invariant — that's `absence` territory, not a type lie.
- **Modelling real missingness** — a finder that may find nothing, a config that may be unset. Use
  `absence`: `Option`, an empty default, or a throw. The lie is only when the value is *certain* and typed
  as if it weren't.

### The tell

You're re-proving, on every read, something the design already guarantees: `?->` on your own field, a
`?? <literal>` whose branch can't be reached, a `$prev = $this->x; … $this->x = $prev`. Ask: *is this value
ever actually absent here?* If no, the type is lying — move the value into the signature, a non-nullable
field, or a value object, and delete the defence.

## Rules

- [ ] Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`.
- [ ] If a nullable field is assumed present everywhere its value flows and guarded nowhere, the null is a lie — make it non-nullable and let it be required, failing hard at construction on a real miss.
- [ ] Leave the return type off an arrow function whose expression already proves the type. Declare one when the type is genuinely ambiguous or you are narrowing it — never to restate a property or a method you can read from here.
      _drop the `: Type` — `repent` does this for you_
- [ ] Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.
- [ ] A required slot means the caller has the value. Filling it to satisfy the signature makes the envelope lie in a way no type can catch.
      _Fetch the real value, or split a narrower envelope that only promises what this answer knows._
- [ ] A property hook must EARN its hook: a `get` body that references no `$this` (and no `parent::`) computes nothing from the object — it yields the same value however the instance is configured, so it is a plain property in disguise. This usually happens when an interface declares `{ get; }` and the implementer mimics the syntax; a plain property satisfies a hooked interface property just as well.
      _Make it a stored property: a constant body becomes a property default (`public ?Transition $t = null;`); a constructed value (`get => Transition::make(...)`) is assigned ONCE in the constructor. Keep the hook only when the body genuinely derives from `$this` state._

## Worked example

### masked-invariant

Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible "not set yet"

```php
----------[ Bad ]----------

public function covers(string $date): bool
{
    return $this->period?->includes($date) ?? false;
}

----------[ Good ]----------

// The FIX for {@see GradeSelector}: the invariant is made CERTAIN instead of masked — the batch is
// held non-nullable (a grading pass without one cannot be constructed), so the read is a plain
// `$this->batch->permits($sku)` with no `?->` and no fake `?? false` answering an impossible state.

final class OpenGradeSelector
{
    public function __construct(private readonly ActiveBatch $batch) {}

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    public function passing(array $skus): array
    {
        return array_values(array_filter($skus, fn (string $sku) => $this->batch->permits($sku)));
    }

    public function accepts(string $sku): bool
    {
        return $this->batch->permits($sku);
    }
}
```

The other 5 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/type-honesty` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `masked-invariant`, `phantom-nullable`, `redundant-arrow-return-type`, `scratch-state-restore`, `placeholder-filled-data`, `useless-property-hook`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `redundant-arrow-return-type`. Review it with `--dry-run` first.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 6 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/absence`](../absence/SKILL.md) — the complement: absence models a genuine maybe-missing; this kills a FAKE one.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is born, not defended at every read.
