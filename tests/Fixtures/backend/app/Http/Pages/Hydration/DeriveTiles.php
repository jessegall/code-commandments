<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DerivedCollectionCast;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/*
 * N2 scenario 3 — a named factory mapped over a numeric range inside a guarded, capped method. Plus the
 * righteous twins: a service-closed factory (a cast can't reach `$this`), and a plain-scalar map.
 */
final class TileGrid extends Data
{
    public function __construct(
        #[DataCollectionOf(GridTile::class)]
        public readonly array $tiles,
    ) {}
}

final class TileGridFactory
{
    private const int MAX = 64;

    #[Sinful(DerivedCollectionCast::class)]
    public function ofSize(int $size): TileGrid
    {
        $size = min($size, self::MAX);

        if ($size < 1) {
            return TileGrid::from(['tiles' => []]);
        }

        return TileGrid::from(['tiles' => array_map(GridTile::make(...), range(0, $size - 1))]);
    }

    public function capacity(): int
    {
        return self::MAX;
    }
}

/**
 * RIGHTEOUS: the factory closes over `$this->theme`, so it can't move into a per-item cast; and a map to
 * plain scalars isn't a collection derivation. Neither is flagged.
 */
final class ThemedLegend extends Data
{
    public function __construct(
        #[DataCollectionOf(StateChip::class)]
        public readonly array $chips,
    ) {}
}

final class ThemedLegendBuilder
{
    private string $theme = 'dark';

    #[Righteous(DerivedCollectionCast::class)]
    public function build(): ThemedLegend
    {
        return ThemedLegend::from(['chips' => array_map(fn (ShipState $s) => StateChip::themed($s, $this->theme), ShipState::cases())]);
    }
}

final class LabelStrip extends Data
{
    /**
     * @var list<string>
     */
    public function __construct(public readonly array $labels) {}
}

final class LabelStripBuilder
{
    #[Righteous(DerivedCollectionCast::class)]
    public function build(): LabelStrip
    {
        $chips = [StateChip::for(ShipState::Pending), StateChip::for(ShipState::Shipped)];

        return LabelStrip::from(['labels' => array_map(static fn (StateChip $c): string => $c->label, $chips)]);
    }
}
