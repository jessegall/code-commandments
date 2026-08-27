<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllNullableData;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;

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
#[Fixed(ArrayBag::class)]
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
 * The FIX for {@see LegacyImportRow}: every field retyped to the truth. The three the importer cannot
 * work without are non-nullable and undefaulted, so `::from()` fails hard on a malformed row; the one
 * that is GENUINELY absent-or-present is `string|Optional = new Optional()`, which the wire OMITS
 * rather than shipping as `null`. No `?? 0` is needed anywhere downstream.
 */
#[Fixed(AllNullableData::class)]
final class RetypedImportRow extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $quantity,
        public readonly int $priceCents,
        public readonly string|Optional $note = new Optional(),
    ) {}

    public function lineTotal(): int
    {
        return $this->quantity * $this->priceCents;
    }

    public function annotated(): string
    {
        return $this->note instanceof Optional ? $this->sku : $this->sku . ' — ' . $this->note;
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
