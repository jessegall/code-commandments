<?php

namespace App\Inventory;

class Warehouse
{
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';

    public $code;
    public $stock;
    public $status;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        $this->code = $data['code'] ?? null;
        $this->stock = $data['stock'] ?? null;
        $this->status = $data['status'] ?? self::STATUS_PENDING;
    }

    public function sku()
    {
        return $this->stock ? $this->stock->sku : null;
    }

    public function canFulfil($quantity)
    {
        if ($this->status == 'active') {
            return $this->stock && $this->stock->canCover($quantity);
        } elseif ($this->status == 'pending') {
            return false;
        } else {
            return false;
        }
    }

    public function withdraw($quantity)
    {
        if ($this->stock) {
            $this->stock->reduceBy($quantity);
        }
    }
}
