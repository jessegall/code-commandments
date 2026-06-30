<?php

namespace Shop\Data;

use Spatie\LaravelData\Data;

/**
 * Typed view of a courier's tracking response — the shape we pin down at the boundary.
 */
final class TrackingStatus extends Data
{
    public function __construct(
        public readonly string $code,
        public readonly string $state,
        public readonly string $location,
        public readonly ?string $deliveredAt = null,
    ) {}
}
