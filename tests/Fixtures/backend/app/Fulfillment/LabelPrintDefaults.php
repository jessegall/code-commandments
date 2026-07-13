<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\UselessPropertyHook;

use JesseGall\CodeCommandments\Testing\Sinful;

use Shop\ValueObjects\Weight;

/**
 * Rebuilds the SAME value object on every read — nothing comes from `$this`, so the
 * construction belongs in the constructor (a `new`/static call can't be a property
 * default), not in a per-read hook.
 */
#[Sinful(UselessPropertyHook::class)]
final class LabelPrintDefaults
{
    public Weight $maxParcelWeight {
        get => new Weight(23000);
    }

    public function __construct(
        private readonly string $printerId,
        private readonly bool $duplex = false,
    ) {}

    public function printerId(): string
    {
        return $this->printerId;
    }

    public function copiesFor(int $parcels): int
    {
        return $this->duplex ? (int) ceil($parcels / 2) : $parcels;
    }

    public function describe(): string
    {
        return sprintf('%s (%s)', $this->printerId, $this->duplex ? 'duplex' : 'simplex');
    }
}
