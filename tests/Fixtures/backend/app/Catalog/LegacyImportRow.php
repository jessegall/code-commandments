<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\AllNullableData;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A row from the legacy CSV importer — defaulted to nothing on every field, so a
 * malformed row is indistinguishable from a valid one.
 */
#[Sinful(AllNullableData::class)]
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

/**
 * The same row with its required fields non-nullable: `::from()` fails hard on a
 * real miss, so a valid row can't be confused with a malformed one.
 */
#[Righteous(AllNullableData::class)]
final class ImportRow extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $quantity,
        public readonly ?int $priceCents = null,
        public readonly ?string $note = null,
    ) {}

    public function lineTotal(): int
    {
        return $this->quantity * ($this->priceCents ?? 0);
    }
}
