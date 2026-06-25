<?php

namespace App\Orders;

class Money
{
    public $cents;
    public $currency;

    public function __construct($cents = null, $currency = null)
    {
        $this->cents = (int) ($cents ?? 0);
        $this->currency = $currency ?? 'USD';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function fromArray(array $data): array
    {
        return [
            'cents' => (int) ($data['cents'] ?? 0),
            'currency' => $data['currency'] ?? 'USD',
        ];
    }

    public function add($other)
    {
        return new Money($this->cents + $other->cents, $this->currency);
    }

    public function multiply($factor)
    {
        return new Money($this->cents * (int) $factor, $this->currency);
    }
}
