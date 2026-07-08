<?php

namespace Shop\Http\Pages\FlatCluster;

use Spatie\LaravelData\Data;

/* The Coord value object: a geographic point, {lat, lng}. */
final class Coord extends Data
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
    ) {}
}
