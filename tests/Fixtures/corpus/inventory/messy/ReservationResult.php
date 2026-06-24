<?php

namespace App\Inventory;

class ReservationResult
{
    public $warehouseCode;
    public $quantity;
    public $fulfilled;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->warehouseCode = $data['warehouseCode'] ?? null;
        $this->quantity = (int) ($data['quantity'] ?? 0);
        $this->fulfilled = $data['fulfilled'] ?? false;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return compact('warehouseCode', 'quantity', 'fulfilled');
    }
}
