<?php

namespace App\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types the inbound catalog search payload.
 */
final class ProductSearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'term' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'max_price_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function term(): string
    {
        return $this->string('term');
    }

    public function type(): ?ProductType
    {
        if (! $this->has('type')) {
            return null;
        }

        return $this->enum('type', ProductType::class);
    }

    public function maxPriceCents(): int
    {
        return $this->integer('max_price_cents');
    }

    public function toSearch(): ProductSearch
    {
        return new ProductSearch(
            term: $this->term(),
            type: $this->type(),
            maxPriceCents: $this->maxPriceCents(),
        );
    }
}
