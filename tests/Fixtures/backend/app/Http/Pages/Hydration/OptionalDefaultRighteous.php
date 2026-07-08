<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/*
 * Righteous twin for NullToOptionalMap: the CORRECT shapes. A `T | Optional` slot DEFAULTS to `new Optional`
 * (a parameter default, not a hand-rolled map), and a bare `return new Optional` in a guard. Neither maps
 * null→Optional at a producer, so neither is flagged. (A concrete `label` keeps it off AllOptionalData.)
 */
#[Righteous(NullToOptionalMap::class)]
final class Placement extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly OptCoords|Optional $at = new Optional(),
    ) {}

    public function fallback(bool $absent): OptCoords|Optional
    {
        if ($absent) {
            return new Optional();
        }

        return OptCoords::from([]);
    }
}
