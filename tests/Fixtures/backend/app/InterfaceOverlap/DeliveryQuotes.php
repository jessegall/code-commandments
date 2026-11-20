<?php

namespace Shop\InterfaceOverlap\Delivery;

interface DeliveryQuotes
{
    public static function reserve(string $country, float $weight, array $options = []): string;
    public function prices(\DateTimeImmutable ...$departures): array;
    public function release(string $reservation, bool $notify): void;
    public function surcharge(string $reservation): float|null;
    public function currency(): string;
    public function terminals(): iterable;
}
