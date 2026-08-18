# Spatie Data — feed the framework, don't hand-build — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### data-to-array-roundtrip

A `X::from(...)->toArray()` sits in a `::from` slot typed `X` that re-hydrates it — build → array → build

```php
----------[ Bad ]----------

public function hold(BadgeCopy $badge, string $status): BadgeHolder
{
    $toned = new BadgeCopy($badge->label, $this->toneFor($status));

    return BadgeHolder::from(['badge' => $toned->toArray()]);
}

----------[ Good ]----------

// The FIX: the `badge` slot is typed `BadgeCopy`, so it takes the object as-is — no `->toArray()`,
// no rebuild.

public function holdReady(BadgeCopy $badge, string $status): BadgeHolder
{
    $toned = new BadgeCopy($badge->label, $this->toneFor($status));

    return BadgeHolder::from(['badge' => $toned]);
}
```

### derived-collection-cast

A `#[DataCollectionOf]` is filled by mapping a factory over inputs at the call site, where a `#[WithCast]` should own the derivation

```php
----------[ Bad ]----------

public function build(): ShipStatusLegend
{
    return ShipStatusLegend::from(['chips' => array_map(StateChip::for(...), ShipState::cases())]);
}

----------[ Good ]----------

// The FIX: the raw enum cases are passed straight in — the `#[WithCast(StateChipCast::class)]` on the
// `chips` property derives each `StateChip`, so there is no `array_map` at the call site.

public function buildCast(): CastShipStatusLegend
{
    return CastShipStatusLegend::from(['chips' => ShipState::cases()]);
}
```

### hand-key-remap

A `::from([...])` mechanically renames `$src['snake_key']` → `camelKey` by hand, instead of a class-level `#[MapInputName]`

```php
----------[ Bad ]----------

public function import(): ContractData
{
    $src = $this->rows->next();

    return ContractData::from([
        'recordCompany' => $src['record_company'],
        'signedAt' => $src['signed_at'],
    ]);
}

----------[ Good ]----------

// The FIX: the source row is passed WHOLE — the class-level `#[MapInputName(SnakeCaseMapper::class)]`
// does the snake→camel translation, so no caller writes it out again.

public function importMapped(): MappedContractData
{
    return MappedContractData::from($this->rows->next());
}
```

### redundant-enum-unwrap

An enum is unwrapped to `->value` at a hydration site (`'status' => $order->status->value`) where the property is typed as that enum — Spatie re-casts the scalar straight back to the enum

```php
----------[ Bad ]----------

public function summarise(Basket $basket): CheckoutSummary
{
    return CheckoutSummary::from(['status' => $basket->status->value, 'lines' => count($basket->items)]);
}

----------[ Good ]----------

// The FIX: the enum itself goes into its own enum slot — Spatie's enum cast keeps it, so there is
// nothing to unwrap and re-hydrate.

public function summariseWhole(Basket $basket): CheckoutSummary
{
    return CheckoutSummary::from(['status' => $basket->status, 'lines' => count($basket->items)]);
}
```

### redundant-native-cast

An enum / date is constructed at a hydration site (`Enum::from($x)`, `new DateTime($x)`) where the property auto-casts the raw scalar

```php
----------[ Bad ]----------

public function fromCode(string $code): OrderState
{
    return OrderState::from(['state' => FulfilmentState::from($code), 'caption' => $this->captionFor($code)]);
}

----------[ Good ]----------

// The FIX: the raw code goes straight into the `state` slot — Spatie's native enum cast builds the
// `FulfilmentState` from it.

public function fromRawCode(string $code): OrderState
{
    return OrderState::from(['state' => $code, 'caption' => $this->captionFor($code)]);
}
```

### redundant-nested-from

A nested `X::from([...])` fills a slot the parent `::from` already auto-hydrates from the array

```php
----------[ Bad ]----------

public function build(int $count): BadgeStrip
{
    return BadgeStrip::from(['badge' => BadgeCopy::from(['label' => $this->pluralise($count), 'tone' => 'info'])]);
}

----------[ Good ]----------

// The FIX: the plain array goes straight into the `badge` slot — the parent `::from` hydrates the
// nested `BadgeCopy` itself.

public function buildPlain(int $count): BadgeStrip
{
    return BadgeStrip::from(['badge' => ['label' => $this->pluralise($count), 'tone' => 'info']]);
}
```
