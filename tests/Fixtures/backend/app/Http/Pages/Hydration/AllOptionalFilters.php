<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllOptionalData;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/*
 * Scenario 3 — a search-filter envelope, every criterion Optional. A query-building fold over the present
 * criteria gives it a distinct shape from the grid and surface bags; the same all-optional smell.
 */
#[Sinful(AllOptionalData::class)]
final class OrderFilters extends Data
{
    public function __construct(
        public readonly string|Optional $status = new Optional(),
        public readonly string|Optional $customer = new Optional(),
    ) {}

    public function activeCount(): int
    {
        $count = 0;

        foreach ([$this->status, $this->customer] as $criterion) {
            if (! $criterion instanceof Optional) {
                $count++;
            }
        }

        return $count;
    }

    public function summary(): string
    {
        if ($this->status instanceof Optional && $this->customer instanceof Optional) {
            return 'unfiltered';
        }

        $status = $this->status instanceof Optional ? 'any' : $this->status;
        $customer = $this->customer instanceof Optional ? 'any' : $this->customer;

        return "status={$status}, customer={$customer}";
    }
}
