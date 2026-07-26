<?php

namespace Shop\Http\Pages\Hydration;

use Spatie\LaravelData\Data;

/**
 * A small value Data the optional-map producer fixtures hydrate.
 */
final class OptCoords extends Data
{
    public function __construct(
        public readonly int $x = 0,
        public readonly int $y = 0,
    ) {}
}
