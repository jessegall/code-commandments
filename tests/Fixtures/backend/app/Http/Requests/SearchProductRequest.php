<?php

namespace Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string'],
            'category' => ['nullable', 'string'],
        ];
    }

    public function term(): string
    {
        return $this->string('q')->toString();
    }

    public function category(): string
    {
        return $this->string('category')->toString();
    }
}
