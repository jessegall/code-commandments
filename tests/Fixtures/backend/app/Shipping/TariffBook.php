<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;
use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Testing\Sinful;

final readonly class Tariff
{
    public function __construct(public int $cents, public string $label) {}
}

final class TariffBook
{
    /**
     * @param  array<string, Tariff>  $tariffs
     */
    public function __construct(private array $tariffs) {}

    #[Sinful(DeNulledFinder::class)]
    #[Sinful(NullableRegistryLookup::class)]
    public function forZone(string $zone): ?Tariff
    {
        return $this->tariffs[$zone] ?? null;
    }

    public function cents(string $zone): int
    {
        return $this->forZone($zone)->cents;
    }

    public function label(string $zone): string
    {
        return $this->forZone($zone)->label;
    }
}
