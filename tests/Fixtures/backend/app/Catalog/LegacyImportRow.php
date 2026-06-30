<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\AllNullableDataDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A row from the legacy CSV importer — defaulted to nothing on every field, so a
 * malformed row is indistinguishable from a valid one.
 */
#[Sinful(AllNullableDataDetector::class)]
final class LegacyImportRow extends Data
{
    public function __construct(
        public readonly string $sku = '',
        public readonly int $quantity = 0,
        public readonly ?int $priceCents = null,
        public readonly ?string $note = null,
    ) {}

    public function lineTotal(): int
    {
        return $this->quantity * ($this->priceCents ?? 0);
    }
}
