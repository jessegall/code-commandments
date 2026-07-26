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
- If a nullable field is assumed present everywhere its value flows and guarded nowhere, the null is a lie — make it non-nullable and let it be required, failing hard at construction on a real miss.
- Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.
- A required slot means the caller has the value. Filling it to satisfy the signature makes the envelope lie in a way no type can catch.
  _Fetch the real value, or split a narrower envelope that only promises what this answer knows._
- A property hook must EARN its hook: a `get` body that references no `$this` (and no `parent::`) computes nothing from the object — it yields the same value however the instance is configured, so it is a plain property in disguise. This usually happens when an interface declares `{ get; }` and the implementer mimics the syntax; a plain property satisfies a hooked interface property just as well.
  _Make it a stored property: a constant body becomes a property default (`public ?Transition $t = null;`); a constructed value (`get => Transition::make(...)`) is assigned ONCE in the constructor. Keep the hook only when the body genuinely derives from `$this` state._

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
final class PackingSlip
{
    public ?ShippingAddress $deliverTo = null;

    public function attach(ShipmentLabel $label): void
    {
        $label->deliverTo = $this->deliverTo;
    }
}

// Good
final class DeliveryInstruction
{
    public function __construct(
        public readonly ?string $note,
        public readonly bool $signatureRequired,
    ) {}
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

```php
// Bad
public function draft(string $slug): WorkflowRowData
{
    $name = $slug === '' ? self::UNTITLED : $slug;

    return new WorkflowRowData($slug, $name, null, false, '');
}

// Good
public function publish(string $slug, string $name, string $stamp): WorkflowRowData
{
    return new WorkflowRowData($slug, $name, null, true, $stamp);
}
```

```php
// Bad
final class LabelPrintDefaults
{
    public Weight $maxParcelWeight {
        get => new Weight(23000);
    }

    public function __construct(
        private readonly string $printerId,
        private readonly bool $duplex = false,
    ) {}

    public function printerId(): string
    {
        return $this->printerId;
    }

    public function copiesFor(int $parcels): int
    {
        return $this->duplex ? (int) ceil($parcels / 2) : $parcels;
    }

    public function describe(): string
    {
        return sprintf('%s (%s)', $this->printerId, $this->duplex ? 'duplex' : 'simplex');
    }
}

// Good
final class GlowingTile implements AnimatedTile
{
    private string $easingMode = 'in';

    /**
     * Derived from own state — a real computed property.
     */
    public ?string $enterEffect { get => $this->intensity > 5 ? 'flash' : 'fade'; }

    /**
     * Delegates to own behaviour — still reads the instance.
     */
    public ?string $leaveEffect { get => $this->resolveLeave(); }

    /**
     * A get/set pair is judged as a unit — the setter earns the hook syntax.
     */
    public string $easing {
        get => 'ease-' . $this->easingMode;
        set => strtolower($value);
    }

    public function __construct(private readonly int $intensity) {}

    private function resolveLeave(): ?string
    {
        return $this->intensity > 0 ? 'shrink' : null;
    }
}
```

## When it fires

- Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible "not set yet" — `MaskedInvariantDetector`
- Phantom nullable — a field typed `?T` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens — `PhantomNullableDetector`
- Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input — `ScratchStateRestoreDetector`
- A required non-nullable `string` slot handed `''` — the type promises a value that is always there and the caller has none — `PlaceholderFilledDataDetector`
- A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax — `UselessPropertyHookDetector`

## Checklist

- [ ] Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`.
- [ ] If a nullable field is assumed present everywhere its value flows and guarded nowhere, the null is a lie — make it non-nullable and let it be required, failing hard at construction on a real miss.
- [ ] Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.
- [ ] A required slot means the caller has the value. Filling it to satisfy the signature makes the envelope lie in a way no type can catch.
- [ ] A property hook must EARN its hook: a `get` body that references no `$this` (and no `parent::`) computes nothing from the object — it yields the same value however the instance is configured, so it is a plain property in disguise. This usually happens when an interface declares `{ get; }` and the implementer mimics the syntax; a plain property satisfies a hooked interface property just as well.

## Related skills

- [`backend/absence`](../absence/SKILL.md) — the complement: absence models a genuine maybe-missing; this kills a FAKE one.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is born, not defended at every read.
