<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataCollectionType;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/*
 * Scenario 2 — a currency ledger whose entries are a nullable `DataCollection`, with money formatting.
 */
#[Sinful(DataCollectionType::class)]
final class LedgerView extends Data
{
    /** @var DataCollection<int, Entry>|null */
    public function __construct(
        public readonly string $currency,
        public readonly int $openingCents,
        public readonly DataCollection|null $entries = null,
    ) {}

    public function opening(): string
    {
        return $this->currency . ' ' . number_format($this->openingCents / 100, 2);
    }

    public function overdrawn(): bool
    {
        return $this->openingCents < 0;
    }
}

final class Entry extends Data
{
    public function __construct(public readonly int $deltaCents) {}
}
