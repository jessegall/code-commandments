<?php

namespace Shop\Api;

use Spatie\LaravelData\Data;

/**
 * The customer payload — camelCase properties the frontend mirrors in snake_case; the
 * matcher normalises the spelling, so the drift is caught either way.
 */
final class CustomerData extends Data
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $emailAddress,
        public readonly string $phoneNumber,
    ) {}
}
