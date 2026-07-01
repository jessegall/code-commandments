<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NonFinalData;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * Stock level transfer object, left non-final.
 */
#[Sinful(NonFinalData::class)]
class StockLevelData extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $onHand,
        public readonly int $reserved,
    ) {}

    public function available(): int
    {
        return max(0, $this->onHand - $this->reserved);
    }

    public function isLow(): bool
    {
        return $this->available() < 5;
    }

    public function status(): string
    {
        return match (true) {
            $this->available() === 0 => 'out-of-stock',
            $this->isLow() => 'low',
            default => 'ok',
        };
    }
}

/**
 * The same DTO sealed: a value type is a leaf, not a base to extend.
 */
#[Righteous(NonFinalData::class)]
final class StockSnapshotData extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $available,
        public readonly string $status,
    ) {}
}
