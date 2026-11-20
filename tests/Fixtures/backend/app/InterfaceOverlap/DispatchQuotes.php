<?php

namespace Shop\InterfaceOverlap\Delivery\Dispatch;

use JesseGall\CodeCommandments\Sins\Shared\InterfaceOverlap;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

#[Sinful(InterfaceOverlap::class)]
interface DispatchQuotes
{
    public function terminals(): iterable;
    public function surcharge(string $reservation): ?float;
    public static function reserve(string $country, float $weight, array $options = []): string;
    public function release(string $reservation, bool $notify): void;
    public function currency(): string;
    public function prices(\DateTimeImmutable ...$departures): array;
}

#[Righteous(InterfaceOverlap::class)]
interface MultiCurrencyQuote
{
    public function price(string $country, int $weight): int;
    public function currency(): string;
}
