<?php

namespace App\Inventory;

class StockLevel
{
    public $sku;
    public $available;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        $this->sku = $data['sku'] ?? null;
        $this->available = (int) ($data['available'] ?? 0);
    }

    public function canCover($quantity)
    {
        return $this->available >= $quantity;
    }

    public function reduceBy($quantity)
    {
        $this->available = $this->available - $quantity;

        return $this;
    }
}
