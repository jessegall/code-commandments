<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledRegistry;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The carriers the shop ships with, each kept under its carrier code.
 */
#[Sinful(HandRolledRegistry::class)]
final class CarrierTable
{
    private array $carriers = [];

    public function add(string $code, string $carrierName): void
    {
        $this->carriers[$code] = $carrierName;
    }

    public function nameOf(string $code): string
    {
        return $this->carriers[$code] ?? throw CarrierUnknown::forCode($code);
    }
}
