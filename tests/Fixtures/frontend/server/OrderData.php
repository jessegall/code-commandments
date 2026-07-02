<?php

namespace Shop\Api;

use Spatie\LaravelData\Data;

/**
 * The order payload an endpoint returns. Its shape is the contract the frontend must
 * NOT hand-copy — mark it `#[TypeScript]` and generate the type.
 */
final class OrderData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly int $total,
        public readonly string $placedAt,
        public readonly string $status,
    ) {}
}
