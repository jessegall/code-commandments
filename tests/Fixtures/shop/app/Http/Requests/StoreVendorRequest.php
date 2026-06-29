<?php

namespace Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Righteous twin for NearDuplicateFunctionDetector (with StoreWarehouseRequest):
 * every FormRequest `rules()` shares the same validation-array skeleton by contract,
 * so the structural twin must NOT be flagged as a near-duplicate.
 */
class StoreVendorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'vat_number' => ['nullable', 'string', 'max:64'],
            'website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
