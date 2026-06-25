<?php

namespace Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
