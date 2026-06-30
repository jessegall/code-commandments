<?php

namespace Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Righteous twin for NearDuplicateFunctionDetector (with StoreVendorRequest): a
 * `rules()` of the same shape on a different request — inherent to the framework
 * contract, not duplication to extract.
 */
class StoreWarehouseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'email', 'max:255'],
            'line' => ['required', 'string', 'max:32'],
            'region' => ['required', 'string', 'size:2'],
            'code' => ['nullable', 'string', 'max:64'],
            'map_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
