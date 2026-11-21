# Spatie Data — feed the framework, don't hand-build — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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
// The FIX: the raw enum cases are passed straight in — the `#[WithCast(StateChipCast::class)]` on the
// `chips` property derives each `StateChip`, so there is no `array_map` at the call site.

public function buildCast(): CastShipStatusLegend
{
    return CastShipStatusLegend::from(['chips' => ShipState::cases()]);
}

// The cast that OWNS the `ShipState → StateChip` derivation — declared once on the collection property,
// so every call site hands over the raw enum cases.

final class StateChipCast
{
    public function cast(ShipState $state): StateChip
    {
        return StateChip::for($state);
    }
}
```
