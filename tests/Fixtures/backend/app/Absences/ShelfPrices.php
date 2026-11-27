<?php

namespace Shop\Absences;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The prices printed on shelf labels, looked up by the SKU a label shows.
 */
final class ShelfPrices
{
    public function __construct(private readonly Collection $cents) {}

    #[Sinful(CoalescedPastDefault::class)]
    public function labelTotal(array $skus): int
    {
        $total = 0;
        foreach ($skus as $sku) {
            $total += $this->cents->get($sku) ?? 0;
        }

        return $total;
    }

    #[Righteous(CoalescedPastDefault::class)]
    public function cheapestOr(string $sku): int
    {
        return $this->cents->firstWhere('sku', $sku) ?? $this->cents->min();
    }
}
