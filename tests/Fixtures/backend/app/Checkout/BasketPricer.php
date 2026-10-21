<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Sins\Backend\FilledParameter;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Prices a basket: the line total, then the tax on it, then the rounding the till needs. Each
 * step writes its answer onto the slip it was handed and hands nothing back, so the caller
 * below reads three figures off a slip whose signature never said they would be there.
 */
final class BasketPricer
{
    /**
     * @param array<int, int> $lines
     */
    public function price(array $lines, int $taxPercent): int
    {
        $slip = new BasketSlip();
        $this->addLines($slip, $lines);
        $this->addTax($slip, $taxPercent);

        return $slip->netCents + $slip->taxCents;
    }

    /**
     * @param array<int, int> $lines
     */
    #[Sinful(FilledParameter::class)]
    #[Sinful(FeatureEnvy::class)]
    private function addLines(BasketSlip $slip, array $lines): void
    {
        $slip->netCents = array_sum($lines);
    }

    #[Sinful(FilledParameter::class)]
    #[Sinful(FeatureEnvy::class)]
    private function addTax(BasketSlip $slip, int $taxPercent): void
    {
        $slip->taxCents = intdiv($slip->netCents * $taxPercent, 100);
    }
}
