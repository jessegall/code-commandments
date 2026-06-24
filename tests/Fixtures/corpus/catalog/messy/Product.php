<?php

namespace App\Catalog;

class Product
{
    /**
     * @var array<string, mixed>
     */
    public $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getSku()
    {
        return $this->data['sku'] ?? null;
    }

    public function getName()
    {
        return $this->data['name'] ?? '';
    }

    public function getType()
    {
        return $this->data['type'] ?? null;
    }

    public function getPrice()
    {
        return $this->data['price'] ?? null;
    }

    public function priceFor($quantity)
    {
        $price = $this->data['price'] ?? null;

        if (is_array($price)) {
            return (int) ($price['amount_cents'] ?? 0) * (int) ($quantity ?? 1);
        }

        return 0;
    }
}
