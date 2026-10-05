<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * One movement of stock, replayed from the warehouse's journal, where a restock flag is written only when it is set.
 */
final class StockMovement
{
    public function __construct(public readonly string $sku, public readonly int $units, public readonly bool $restocks = false) {}

    /**
     * Righteous twin: the fallback is the default the parameter declares, so a movement the journal wrote without
     * the flag becomes exactly what leaving the argument out would; nothing is invented.
     *
     * @param  object{sku: string, units: int, restocks?: bool}  $entry
     */
    #[Righteous(ManufacturedFakeFill::class)]
    public static function replayed(object $entry): self
    {
        return new self($entry->sku, $entry->units, $entry->restocks ?? false);
    }

    public function signedUnits(): int
    {
        return $this->restocks ? $this->units : -$this->units;
    }
}
