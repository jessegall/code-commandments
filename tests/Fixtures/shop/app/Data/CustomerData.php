<?php

namespace Shop\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Typed view of a customer. The snake_case input map is what `::from()` runs and a
 * raw `new` skips — so this class is RICH: `new CustomerData(...)` is a real sin.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class CustomerData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}
