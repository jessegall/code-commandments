<?php

namespace Shop\Pricing\Lookup;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Sins\Backend\MiddleMan;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The price book the storefront asks for prices. It owns no prices of its own: the tariff sheet answers.
 */
#[Sinful(MiddleMan::class)]
final class PriceBook
{
    public function __construct(private readonly TariffSheet $sheet) {}

    public function priceOf(string $sku, int $quantity): int
    {
        return $this->sheet->priceOf($sku, $quantity);
    }

    public function currencies(): array
    {
        return $this->sheet->currencies();
    }

    public function isListed(string $sku): bool
    {
        return $this->sheet->isListed($sku);
    }
}

/**
 * Prices per SKU in cents, with a volume discount.
 */
final class TariffSheet
{
    /**
     * @param  array<string, int>  $cents
     */
    public function __construct(private readonly array $cents) {}

    public function priceOf(string $sku, int $quantity): int
    {
        $unit = $this->cents[$sku] ?? 0;

        return $quantity >= 10 ? intdiv($unit * $quantity * 95, 100) : $unit * $quantity;
    }

    public function currencies(): array
    {
        return ['EUR', 'USD'];
    }

    public function isListed(string $sku): bool
    {
        return array_key_exists($sku, $this->cents);
    }
}

/**
 * Prices with a cache in front: it forwards too, but it keeps the contract the storefront depends on, which a
 * class of its own must satisfy.
 */
#[Righteous(MiddleMan::class)]
final class ListedPrices implements Prices
{
    public function __construct(private readonly TariffSheet $sheet) {}

    public function priceOf(string $sku, int $quantity): int
    {
        return $this->sheet->priceOf($sku, $quantity);
    }

    public function isListed(string $sku): bool
    {
        return $this->sheet->isListed($sku);
    }
}

/**
 * What the storefront asks of a price source.
 */
#[Sinful(LoneImplementerInterface::class)]
interface Prices
{
    public function priceOf(string $sku, int $quantity): int;

    public function isListed(string $sku): bool;
}

/**
 * Forwards one question and answers another itself, so it does real work between the caller and the sheet.
 */
#[Righteous(MiddleMan::class)]
final class Quote
{
    public function __construct(private readonly TariffSheet $sheet) {}

    public function priceOf(string $sku, int $quantity): int
    {
        return $this->sheet->priceOf($sku, $quantity);
    }

    public function totalOf(array $lines): int
    {
        return array_sum(array_map(fn (array $line) => $this->sheet->priceOf($line[0], $line[1]), $lines));
    }
}
