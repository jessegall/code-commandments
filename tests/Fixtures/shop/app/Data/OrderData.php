<?php

namespace Shop\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Shop\Enums\OrderStatus;

/**
 * Typed view of an order for the API.
 */
final class OrderData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly OrderStatus $status,
        public readonly int $totalCents,
        #[DataCollectionOf(OrderLineData::class)]
        public readonly array $lines,
    ) {}
}
