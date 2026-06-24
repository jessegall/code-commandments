<?php

namespace App\Inventory;

/**
 * The outcome of a reservation attempt: either a fulfilled warehouse, or empty.
 */
final readonly class ReservationResult
{
    private const NO_WAREHOUSE = 'none';

    private const NO_QUANTITY = 0;

    private function __construct(
        public string $warehouseCode,
        public int $quantity,
        public bool $fulfilled,
    ) {}

    public static function fulfilledBy(Warehouse $warehouse, int $quantity): self
    {
        return new self($warehouse->code, $quantity, true);
    }

    public static function empty(): self
    {
        return new self(self::NO_WAREHOUSE, self::NO_QUANTITY, false);
    }
}
