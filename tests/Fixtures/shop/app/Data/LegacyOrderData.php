<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Detectors\Backend\NonFinalDataDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * An order DTO left open for subclassing — a value object that should be sealed.
 */
#[Sinful(NonFinalDataDetector::class)]
class LegacyOrderData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $totalCents,
    ) {}

    public function isLarge(): bool
    {
        return $this->totalCents > 100_000;
    }
}
