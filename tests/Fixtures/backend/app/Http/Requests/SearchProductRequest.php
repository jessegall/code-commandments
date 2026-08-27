<?php

namespace Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RawRequestInput;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RequestAccessorRecast;

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

    #[Fixed(RawRequestInput::class)]
    public function term(): string
    {
        return $this->string('q')->toString();
    }

    #[Fixed(RequestAccessorRecast::class)]
    public function category(): string
    {
        return $this->string('category')->toString();
    }
}
