<?php

namespace Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
            'lines' => ['required', 'array'],
        ];
    }

    public function customerId(): int
    {
        return $this->integer('customer_id');
    }

    /**
     * @return array<int, mixed>
     */
    public function lines(): array
    {
        return $this->array('lines');
    }
}
