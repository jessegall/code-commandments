<?php

namespace Shop\Lookups;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use PhpOption\Option;

final class PriceStore
{
    private array $prices = [];

    public function has(string $sku): bool
    {
        return isset($this->prices[$sku]);
    }

    public function get(string $sku): int
    {
        return $this->prices[$sku];
    }

    #[Fixed(CheckedThenRead::class)]
    public function find(string $sku): Option
    {
        return Option::fromArraysValue($this->prices, $sku);
    }

    #[Righteous(CheckedThenRead::class)]
    public function remember(string $sku, int $price): void
    {
        if (! isset($this->prices[$sku])) {
            $this->prices[$sku] = $price;
        }
    }
}
