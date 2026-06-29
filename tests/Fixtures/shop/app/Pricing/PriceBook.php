<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Detectors\Backend\NullableRegistryLookupDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A keyed price store that hands back null on a miss — a registry that should
 * resolve-or-throw, pushing an `?int` onto every caller instead.
 */
final class PriceBook
{
    /** @var array<string, int> */
    private array $prices = [];

    public function set(string $sku, int $cents): void
    {
        $this->prices[strtoupper($sku)] = $cents;
    }

    public function cheapest(): ?int
    {
        return $this->prices === [] ? null : min($this->prices);
    }

    public function markdown(string $sku, int $percent): void
    {
        $code = strtoupper($sku);
        $current = $this->prices[$code] ?? 0;
        $this->prices[$code] = (int) ($current * (100 - $percent) / 100);
    }

    #[Sinful(NullableRegistryLookupDetector::class)]
    public function priceFor(string $sku): ?int
    {
        $code = strtoupper(trim($sku));

        if ($code === '') {
            return null;
        }

        return $this->prices[$code] ?? null;
    }
}
