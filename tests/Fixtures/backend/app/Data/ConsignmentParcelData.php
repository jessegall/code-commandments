<?php

namespace Shop\Data;

use Spatie\LaravelData\Data;

/**
 * A parcel of a consignment as the courier's manifest lists it.
 */
final class ConsignmentParcelData extends Data
{
    public function __construct(
        public readonly string $reference,
        public readonly int $grams,
    ) {}
}
