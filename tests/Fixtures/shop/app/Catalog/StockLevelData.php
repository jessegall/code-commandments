<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\NonFinalDataDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * Stock level transfer object, left non-final.
 */
#[Sinful(NonFinalDataDetector::class)]
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
        return $this->available() === 0 ? 'out-of-stock' : ($this->isLow() ? 'low' : 'ok');
    }
}
