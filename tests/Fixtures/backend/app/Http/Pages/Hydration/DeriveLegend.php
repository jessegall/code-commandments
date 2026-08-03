<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DerivedCollectionCast;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/*
 * N2 scenario 1 — an enum vocabulary derived through a FIRST-CLASS CALLABLE over every case. A cast on the
 * `chips` property should own the `state → StateChip` derivation.
 */
final class ShipStatusLegend extends Data
{
    public function __construct(
        #[DataCollectionOf(StateChip::class)]
        public readonly array $chips,
    ) {}
}

/**
 * The cast that OWNS the `ShipState → StateChip` derivation — declared once on the collection property,
 * so every call site hands over the raw enum cases.
 */
final class StateChipCast
{
    public function cast(ShipState $state): StateChip
    {
        return StateChip::for($state);
    }
}

/**
 * The same legend with the derivation moved onto the property: `#[WithCast]` runs `StateChip::for()` per
 * item, so the `chips` slot is filled from the raw list.
 */
final class CastShipStatusLegend extends Data
{
    public function __construct(
        #[WithCast(StateChipCast::class)]
        #[DataCollectionOf(StateChip::class)]
        public readonly array $chips,
    ) {}
}

final class ShipStatusLegendBuilder
{
    #[Sinful(DerivedCollectionCast::class)]
    public function build(): ShipStatusLegend
    {
        return ShipStatusLegend::from(['chips' => array_map(StateChip::for(...), ShipState::cases())]);
    }

    /**
     * The FIX: the raw enum cases are passed straight in — the `#[WithCast(StateChipCast::class)]` on the
     * `chips` property derives each `StateChip`, so there is no `array_map` at the call site.
     */
    #[Fixed(DerivedCollectionCast::class)]
    public function buildCast(): CastShipStatusLegend
    {
        return CastShipStatusLegend::from(['chips' => ShipState::cases()]);
    }

    public function isTerminal(ShipState $state): bool
    {
        return match ($state) {
            ShipState::Shipped, ShipState::Lost => true,
            ShipState::Pending => false,
        };
    }
}
