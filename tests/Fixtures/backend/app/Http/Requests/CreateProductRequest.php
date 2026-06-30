<?php

namespace Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shop\Enums\ProductCategory;

class CreateProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'price_cents' => ['required', 'integer'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function priceCents(): int
    {
        return $this->integer('price_cents');
    }

    public function category(): ProductCategory
    {
        return $this->enum('category', ProductCategory::class);
    }
}
