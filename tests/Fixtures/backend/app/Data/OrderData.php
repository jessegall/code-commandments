<?php

namespace Shop\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Shop\Enums\OrderStatus;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NewDataObject;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * Typed view of an order for the API.
 */
#[Fixed(NewDataObject::class)]
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
