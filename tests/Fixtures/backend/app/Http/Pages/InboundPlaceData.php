<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Computed;

/**
 * `$spot` is hand-built (`new GeoPoint(...)`) in the source assembled a statement before every `::from()`
 * — the same input mapping done by hand at the call site. It belongs in a cast on the property.
 */
final class InboundPlaceData extends Data
{
    #[Sinful(ManualInputCast::class)]
    public function __construct(
        public readonly GeoPoint $spot,
        public readonly float $radiusKm,
        public readonly string $label,
    ) {}

    public function areaSquareKm(): float
    {
        return 3.14159 * $this->radiusKm * $this->radiusKm;
    }

    public function describe(): string
    {
        return $this->label . ' covers ' . $this->areaSquareKm() . ' sqkm';
    }

    #[Computed]
    public bool $isPinpoint {
        get => $this->radiusKm === 0.0;
    }
}
