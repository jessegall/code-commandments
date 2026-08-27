# Type honesty — the type must not lie — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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

### phantom-nullable

Phantom nullable — a field typed `?T` (promoted param or declared property, any class) whose value, traced through the whole program, is always read as present and NEVER guarded, so the null never happens

```php
----------[ Bad ]----------

// The packing slip — carries the delivery address on to the shipment label.

final class PackingSlip
{
    public ?ShippingAddress $deliverTo = null;

    public function attach(ShipmentLabel $label): void
    {
        $label->deliverTo = $this->deliverTo;
    }
}

----------[ Good ]----------

// The FIX for {@see Order}: `$shipTo` is typed `ShippingAddress` — NOT nullable. Every reader
// already assumed it, so the type now says so and construction fails hard on a real miss instead
// of carrying a null nobody ever guards.

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
----------[ Bad ]----------

// Decides whether a named feature is on — the default arm answers "false" for an
// unknown flag, masking a typo as a disabled feature.

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

----------[ Good ]----------

// The FIX: the arrow's one expression already proves the type, so the `: array` comes off —
// `fn () => $this->overrides` says everything the annotation did.

public function overridesReader(): callable
{
    return fn () => $this->overrides;
}
```

### scratch-state-restore

Scratch state on `$this` — a method that saves one of its own fields to a local and restores it (`$prev = $this->scope; … $this->scope = $prev`), the field really a per-call input

```php
----------[ Bad ]----------

public function revalue(string $basis): void
{
    $previous = $this->basis;
    $this->basis = $basis;

    $this->trail[] = sprintf('priced on %s', $this->basis);

    $this->basis = $previous;
}

----------[ Good ]----------

// The FIX: the basis is this call's input, so it stays a PARAMETER and is read from there —
// `$this->basis` is never written, and the save/restore pair disappears with it.

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
----------[ Bad ]----------

public function refuse(string $reason, bool $retryable): AiReplyData
{
    $this->refusals[] = $reason;

    if ($retryable) {
        return new AiReplyData(message: '', success: false, error: 'retry_later');
    }

    return new AiReplyData(message: '', success: false, error: $reason);
}

----------[ Good ]----------

// The FIX for {@see ActivateWorkflow}: the required `updatedAt` slot is handed the REAL value — the
// command holds the clock that owns the timestamp and reads it, instead of filling the promise with
// `''` to satisfy the signature.

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

// The source of the timestamp the fixed activation asks for.

final class WorkflowClock
{
    public function stamp(): string
    {
        return '2024-01-01T00:00:00Z';
    }
}
```

### useless-property-hook

A `get` hook that reads nothing from `$this` — a stored property wearing computed syntax

```php
----------[ Bad ]----------

// Rebuilds the SAME value object on every read — nothing comes from `$this`, so the
// construction belongs in the constructor (a `new`/static call can't be a property
// default), not in a per-read hook.

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

----------[ Good ]----------

// The FIX for {@see TileAnimation}: the same tile with both hooks made STORED properties — the
// constant body became a property default, the constructed one is assigned ONCE in the constructor.
// A plain property satisfies the interface's `{ get; }` just as well as a hook did.

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
