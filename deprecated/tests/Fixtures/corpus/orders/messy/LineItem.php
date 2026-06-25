<?php

namespace App\Orders;

class LineItem
{
    public $sku;
    public $quantity;
    public $unitPrice;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->sku = $data['sku'] ?? null;
        $this->quantity = (int) ($data['quantity'] ?? 1);
        $this->unitPrice = $data['unit_price'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function subtotal()
    {
        $cents = (int) ($this->unitPrice['cents'] ?? 0);
        $currency = $this->unitPrice['currency'] ?? 'USD';

        return [
            'cents' => $cents * $this->quantity,
            'currency' => $currency,
        ];
    }
}
