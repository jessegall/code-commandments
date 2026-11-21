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

// in Shop\Http\Pages\Hydration\BadgeHolderBuilder
public function holdReady(BadgeCopy $badge, string $status): BadgeHolder
{
    $toned = new BadgeCopy($badge->label, $this->toneFor($status));

    return BadgeHolder::from(['badge' => $toned]);
}

final class BadgeCopy extends Data
{
    public function __construct(public readonly string $label, public readonly string $tone) {}
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

// in Shop\Http\Pages\Hydration\ShipStatusLegendBuilder
public function buildCast(): CastShipStatusLegend
{
    return CastShipStatusLegend::from(['chips' => ShipState::cases()]);
}

final class StateChipCast
{
    public function cast(ShipState $state): StateChip
    {
        return StateChip::for($state);
    }
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

// in Shop\Http\Pages\Hydration\ContractImporter
public function importMapped(): MappedContractData
{
    return MappedContractData::from($this->rows->next());
}

#[MapInputName(SnakeCaseMapper::class)]
final class MappedContractData extends Data
{
    public function __construct(
        public readonly string $recordCompany,
        public readonly string $signedAt,
    ) {}
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

// in Shop\Http\Pages\Hydration\CheckoutSummaryFactory
public function summariseWhole(Basket $basket): CheckoutSummary
{
    return CheckoutSummary::from(['status' => $basket->status, 'lines' => count($basket->items)]);
}

final class CheckoutSummaryPage extends Data
{
    #[Computed]
    public int $itemCount { get => count($this->lines); }

    public function __construct(
        public readonly CartLine $lead,
        public readonly StatCard $total,
        /**
         * @var list<CartLine>
         */
        #[DataCollectionOf(CartLine::class)]
        public readonly array $lines,
        #[FromContainer(CartReader::class)]
        public readonly CartReader $cart,
    ) {}

    public function isBackordered(): bool
    {
        return $this->lead->qty > 99;
    }
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

// in Shop\Http\Pages\Hydration\OrderStateBuilder
public function fromRawCode(string $code): OrderState
{
    return OrderState::from(['state' => $code, 'caption' => $this->captionFor($code)]);
}

final class OrderState extends Data
{
    public function __construct(public readonly FulfilmentState $state, public readonly string $caption) {}
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

// in Shop\Http\Pages\Hydration\BadgeStripBuilder
public function buildPlain(int $count): BadgeStrip
{
    return BadgeStrip::from(['badge' => ['label' => $this->pluralise($count), 'tone' => 'info']]);
}

final class BadgeStrip extends Data
{
    public function __construct(public readonly BadgeCopy $badge) {}
}
```
