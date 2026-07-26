<?php

namespace Shop\Events;

/**
 * Raised when a warehouse count matched the ledger. Nothing raises it any more.
 */
final readonly class StockReconciled
{
    public function __construct(public string $warehouseId) {}
}
