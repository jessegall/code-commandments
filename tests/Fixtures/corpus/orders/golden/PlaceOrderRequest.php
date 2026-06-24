<?php

declare(strict_types=1);

namespace App\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types the inbound "place an order" payload.
 */
final class PlaceOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string'],
            'sku' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string'],
        ];
    }

    public function customerId(): string
    {
        return (string) $this->string('customer_id');
    }

    public function sku(): string
    {
        return (string) $this->string('sku');
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }

    public function unitPriceCents(): int
    {
        return $this->integer('unit_price_cents');
    }

    public function currency(): string
    {
        return (string) $this->string('currency');
    }
}
