---
name: commandments-backend-type-honesty
description: A type must tell the truth about the value. Don't fake optionality — a `?T` / nullable that the design always has set, which the code then immediately defends against (`?->`, `?? <fake>`, null-checks) or stashes as mutable scratch state and restores. The defence is the tell that the type is lying. Make the type carry the certainty: pass the value as a parameter, hold it non-nullable, or wrap per-call context in a value object. Read this BEFORE you add a nullable field set later in a method, or reach for `$this->scratch?->… ?? false`.
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

- Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`.
- Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.

## Bad → good

```php
// Bad
public function covers(string $date): bool
{
    return $this->period?->includes($date) ?? false;
}

// Good
public function coversOrFail(string $date): bool
{
    if ($this->period === null) {
        throw LedgerNotFocused::beforeCovers();
    }

    return $this->period->includes($date);
}
```

```php
// Bad
public function nest(string $segment, array $routes): array
{
    $parent = $this->prefix;
    $this->prefix = ltrim($parent . '/' . $segment, '/');

    try {
        return array_map(fn (string $route): string => $this->prefix . '#' . $route, $routes);
    } finally {
        $this->prefix = $parent;
    }
}

// Good
public function nestUnder(string $prefix, string $segment, array $routes): array
{
    $nested = ltrim($prefix . '/' . $segment, '/');

    return array_map(fn (string $route): string => $nested . '#' . $route, $routes);
}
```

## When it fires

- Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible "not set yet" — `MaskedInvariantDetector`
- Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input — `ScratchStateRestoreDetector`

## Checklist

- [ ] Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`.
- [ ] Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.

## Related skills

- [`backend/absence`](../absence/SKILL.md) — the complement: absence models a genuine maybe-missing; this kills a FAKE one.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is born, not defended at every read.
