<?php

namespace Shop\Data;

use Spatie\LaravelData\Data;

/**
 * Typed view of a customer.
 */
final class CustomerData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}
