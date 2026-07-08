<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DerivedCollectionCast;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
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

final class ShipStatusLegendBuilder
{
    #[Sinful(DerivedCollectionCast::class)]
    public function build(): ShipStatusLegend
    {
        return ShipStatusLegend::from(['chips' => array_map(StateChip::for(...), ShipState::cases())]);
    }

    public function isTerminal(ShipState $state): bool
    {
        return match ($state) {
            ShipState::Shipped, ShipState::Lost => true,
            ShipState::Pending => false,
        };
    }
}
