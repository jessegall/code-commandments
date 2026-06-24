<?php

declare(strict_types=1);

namespace App\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types the inbound "reserve stock" payload.
 */
final class ReservationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function sku(): Sku
    {
        return new Sku((string) $this->string('sku'));
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
