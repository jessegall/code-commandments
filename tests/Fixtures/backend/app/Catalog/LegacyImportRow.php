<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\AllNullableData;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A row from the legacy CSV importer — every field nullable, so a malformed row is
 * indistinguishable from a valid one and every consumer must re-validate.
 */
#[Sinful(AllNullableData::class)]
final class LegacyImportRow extends Data
{
    public function __construct(
        public readonly ?string $sku = null,
        public readonly ?int $quantity = null,
        public readonly ?int $priceCents = null,
        public readonly ?string $note = null,
    ) {}

    public function lineTotal(): int
    {
        return ($this->quantity ?? 0) * ($this->priceCents ?? 0);
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

/**
 * An ACCUMULATOR value object — every field non-nullable with a zero identity, plus a
 * `zero()` factory. Not a dodged requirement: zero is the meaningful default for a run
 * that never started, and the type still tells the truth (`int`, not `?int`). NOT this
 * sin, even though every field is optional.
 */
#[Righteous(AllNullableData::class)]
final class ImportTally extends Data
{
    public function __construct(
        public readonly int $rowsRead = 0,
        public readonly int $rowsSkipped = 0,
        public readonly int $rowsImported = 0,
    ) {}

    public static function zero(): self
    {
        return self::from([]);
    }
}
