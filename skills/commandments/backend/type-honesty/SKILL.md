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

- Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`.
- If a nullable field is assumed present everywhere its value flows and guarded nowhere, the null is a lie — make it non-nullable and let it be required, failing hard at construction on a real miss.
- Leave the return type off an arrow function whose expression already proves the type. Declare one when the type is genuinely ambiguous or you are narrowing it — never to restate a property or a method you can read from here.
  _drop the `: Type` — `repent` does this for you_
- Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.
- A required slot means the caller has the value. Filling it to satisfy the signature makes the envelope lie in a way no type can catch.
  _Fetch the real value, or split a narrower envelope that only promises what this answer knows._
- A property hook must EARN its hook: a `get` body that references no `$this` (and no `parent::`) computes nothing from the object — it yields the same value however the instance is configured, so it is a plain property in disguise. This usually happens when an interface declares `{ get; }` and the implementer mimics the syntax; a plain property satisfies a hooked interface property just as well.
  _Make it a stored property: a constant body becomes a property default (`public ?Transition $t = null;`); a constructed value (`get => Transition::make(...)`) is assigned ONCE in the constructor. Keep the hook only when the body genuinely derives from `$this` state._

## Bad → good

### masked-invariant

Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible "not set yet"

```php
// Bad
public function covers(string $date): bool
{
    return $this->period?->includes($date) ?? false;
}

// Good
/**
 * The FIX for {@see GradeSelector}: the invariant is made CERTAIN instead of masked — the batch is
 * held non-nullable (a grading pass without one cannot be constructed), so the read is a plain
 * `$this->batch->permits($sku)` with no `?->` and no fake `?? false` answering an impossible state.
 */
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

### phantom-nullable

Phantom nullable — a field typed `?T` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens

```php
// Bad
/**
 * The packing slip — carries the delivery address on to the shipment label.
 */
final class PackingSlip
{
    public ?ShippingAddress $deliverTo = null;

    public function attach(ShipmentLabel $label): void
    {
        $label->deliverTo = $this->deliverTo;
    }
}

// Good
/**
 * The FIX for {@see Order}: `$shipTo` is typed `ShippingAddress` — NOT nullable. Every reader
 * already assumed it, so the type now says so and construction fails hard on a real miss instead
 * of carrying a null nobody ever guards.
 */
final class ConfirmedOrder
{
    public function __construct(
        public readonly ShippingAddress $shipTo,
        public readonly string $reference,
    ) {}

    public function routingLine(): string
    {
        return $this->reference . ' @ ' . $this->shipTo->postalCode();
    }
}
```

### redundant-arrow-return-type

An arrow function whose return type only repeats what its one expression provably yields — `fn (): string => $this->name` on a `string` property

```php
// Bad
/**
 * Decides whether a named feature is on — the default arm answers "false" for an
 * unknown flag, masking a typo as a disabled feature.
 */
final class FeatureGate
{
    /** @var array<string, bool> */
    private array $overrides = [];

    public function __construct(private readonly string $environment) {}

    /**
     * No argument, so it can only be describing the gate — which is what a question is for.
     */
    public function tracks(): bool
    {
        return $this->environment !== 'testing';
    }

    /**
     * A construction spells the class it builds; the annotation repeats it.
     */
    public function factory(): callable
    {
        return fn (): FeatureGate => new FeatureGate($this->environment);
    }

    /**
     * The FIX: the arrow's one expression already proves the type, so the `: array` comes off —
     * `fn () => $this->overrides` says everything the annotation did.
     */
    public function overridesReader(): callable
    {
        return fn () => $this->overrides;
    }

    public function override(string $flag, bool $on): void
    {
        $this->overrides[$flag] = $on;
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function enabled(string $flag): bool
    {
        return match ($flag) {
            'new-checkout' => true,
            'legacy-import' => false,
            default => false,
        };
    }
}

// Good
/**
 * The FIX: the arrow's one expression already proves the type, so the `: array` comes off —
 * `fn () => $this->overrides` says everything the annotation did.
 */
public function overridesReader(): callable
{
    return fn () => $this->overrides;
}
```

### scratch-state-restore

Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input

```php
// Bad
public function revalue(string $basis): void
{
    $previous = $this->basis;
    $this->basis = $basis;

    $this->trail[] = sprintf('priced on %s', $this->basis);

    $this->basis = $previous;
}

// Good
/**
 * The FIX: the basis is this call's input, so it stays a PARAMETER and is read from there —
 * `$this->basis` is never written, and the save/restore pair disappears with it.
 */
public function revalueOn(string $basis, int $lots): void
{
    for ($lot = 0; $lot < $lots; $lot++) {
        $this->trail[] = sprintf('lot %d priced on %s', $lot, $basis);
    }
}
```

### placeholder-filled-data

A required non-nullable `string` slot handed `''` — the type promises a value that is always there and the caller has none

```php
// Bad
public function refuse(string $reason, bool $retryable): AiReplyData
{
    $this->refusals[] = $reason;

    if ($retryable) {
        return new AiReplyData(message: '', success: false, error: 'retry_later');
    }

    return new AiReplyData(message: '', success: false, error: $reason);
}

// Good
/**
 * The FIX for {@see ActivateWorkflow}: the required `updatedAt` slot is handed the REAL value — the
 * command holds the clock that owns the timestamp and reads it, instead of filling the promise with
 * `''` to satisfy the signature.
 */
final class StampedActivateWorkflow
{
    public function __construct(private readonly WorkflowClock $clock) {}

    public function activate(string $slug): WorkflowRowData
    {
        return new WorkflowRowData(
            slug: $slug,
            name: $slug,
            trigger: null,
            active: true,
            updatedAt: $this->clock->stamp(),
        );
    }
}
```

### useless-property-hook

A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax

```php
// Bad
/**
 * Rebuilds the SAME value object on every read — nothing comes from `$this`, so the
 * construction belongs in the constructor (a `new`/static call can't be a property
 * default), not in a per-read hook.
 */
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
/**
 * The FIX for {@see TileAnimation}: the same tile with both hooks made STORED properties — the
 * constant body became a property default, the constructed one is assigned ONCE in the constructor.
 * A plain property satisfies the interface's `{ get; }` just as well as a hook did.
 */
final class StoredTileAnimation implements AnimatedTile
{
    public ?string $enterEffect = null;

    public ?string $leaveEffect;

    public function __construct(private readonly string $tileId)
    {
        $this->leaveEffect = implode('+', ['fade', 'morph']);
    }

    public function tileId(): string
    {
        return $this->tileId;
    }
}
```

## When it fires

- Masked invariant — a transient own nullable read through `?->… ?? <fake literal>`, the field set inside the operation so the default answers an impossible "not set yet" — `MaskedInvariantDetector`
- Phantom nullable — a field typed `?T` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens — `PhantomNullableDetector`
- An arrow function whose return type only repeats what its one expression provably yields — `fn (): string => $this->name` on a `string` property — `RedundantArrowReturnTypeDetector`
- Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input — `ScratchStateRestoreDetector`
- A required non-nullable `string` slot handed `''` — the type promises a value that is always there and the caller has none — `PlaceholderFilledDataDetector`
- A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax — `UselessPropertyHookDetector`

## Checklist

- [ ] Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`.
- [ ] If a nullable field is assumed present everywhere its value flows and guarded nowhere, the null is a lie — make it non-nullable and let it be required, failing hard at construction on a real miss.
- [ ] Leave the return type off an arrow function whose expression already proves the type. Declare one when the type is genuinely ambiguous or you are narrowing it — never to restate a property or a method you can read from here.
- [ ] Pass a per-call value as a parameter; don't save-and-restore one of your own fields as scratch state.
- [ ] A required slot means the caller has the value. Filling it to satisfy the signature makes the envelope lie in a way no type can catch.
- [ ] A property hook must EARN its hook: a `get` body that references no `$this` (and no `parent::`) computes nothing from the object — it yields the same value however the instance is configured, so it is a plain property in disguise. This usually happens when an interface declares `{ get; }` and the implementer mimics the syntax; a plain property satisfies a hooked interface property just as well.

## Related skills

- [`backend/absence`](../absence/SKILL.md) — the complement: absence models a genuine maybe-missing; this kills a FAKE one.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — make the type certain where the value is born, not defended at every read.
