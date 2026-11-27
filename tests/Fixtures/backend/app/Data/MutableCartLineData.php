<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NonFinalData;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A cart line whose promoted properties can be changed by anyone who holds it.
 */
#[Sinful(NonFinalData::class)]
final class MutableCartLineData extends Data
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {}

    public function isBulk(): bool
    {
        return $this->quantity >= 10;
    }
}

/**
 * The FIX for {@see MutableCartLineData}: the same line with `readonly` promoted properties.
 */
#[Fixed(NonFinalData::class)]
final class CartLineData extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $quantity,
    ) {}

    public function isBulk(): bool
    {
        return $this->quantity >= 10;
    }
}
