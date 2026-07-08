<?php

namespace Shop\Http\Pages\FlatCluster;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\FlatFieldCluster;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * MapMarker restates the Coord value object flat as coord{Lat,Lng}. The two floats are one point that
 * should be nested as `coord: Coord`.
 */
#[TypeScript]
#[Sinful(FlatFieldCluster::class)]
final class MapMarker extends Data
{
    public function __construct(
        public readonly float $coordLat,
        public readonly float $coordLng,
        public readonly string $caption,
    ) {}

    public function hemisphere(): string
    {
        return $this->coordLat >= 0.0 ? 'northern' : 'southern';
    }

    public function nearAntimeridian(): bool
    {
        return abs($this->coordLng) > 170.0;
    }

    public function quadrant(): int
    {
        $vertical = $this->coordLat >= 0.0 ? 0 : 2;
        $horizontal = $this->coordLng >= 0.0 ? 1 : 0;

        return $vertical + $horizontal + 1;
    }

    public function tooltip(): string
    {
        return sprintf('%s (%.2f, %.2f)', $this->caption, $this->coordLat, $this->coordLng);
    }
}
