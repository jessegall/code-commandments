<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * Pattern: coupled optionals. `floor`/`ceil` are one price range — they are null-guarded as a PAIR and
 * then assembled together, so the illegal half-present state (floor set, ceil absent) is representable and
 * the guard only exists to reject it. They should be one `Range|null`.
 */
#[Sinful(CoupledFields::class)]
final class PriceBand
{
    public function __construct(
        public readonly ?int $floor = null,
        public readonly ?int $ceil = null,
        public readonly string $currency = 'EUR',
    ) {}

    public function window(): array
    {
        return $this->floor !== null && $this->ceil !== null ? [$this->floor, $this->ceil] : [];
    }
}

/* The clump extracted: the two halves that only ever moved together ARE the range. */
#[Fixed(CoupledFields::class)]
final readonly class PriceRange
{
    public function __construct(
        public int $floor,
        public int $ceil,
    ) {}
}

/**
 * The same band holding the value object instead of its parts: one `PriceRange|null` says "banded or not"
 * in one field, so the half-present state (floor set, ceil absent) cannot be spelled and the pair-guard
 * that existed only to reject it is gone.
 */
#[Fixed(CoupledFields::class)]
final class BandedPrice
{
    public function __construct(
        public readonly ?PriceRange $range = null,
        public readonly string $currency = 'EUR',
    ) {}
}
