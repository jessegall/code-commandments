<?php

namespace App\Catalog;

class Money
{
    public $amountCents;
    public $currency;

    public function __construct(array $data)
    {
        $this->amountCents = (int) ($data['amount_cents'] ?? 0);
        $this->currency = $data['currency'] ?? 'USD';
    }

    public function add($other)
    {
        return new Money([
            'amount_cents' => $this->amountCents + $other->amountCents,
            'currency' => $this->currency,
        ]);
    }

    public function multipliedBy($quantity)
    {
        return new Money([
            'amount_cents' => $this->amountCents * (int) ($quantity ?? 1),
            'currency' => $this->currency,
        ]);
    }

    public function isFree()
    {
        return $this->amountCents == 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return compact('amountCents', 'currency');
    }
}
