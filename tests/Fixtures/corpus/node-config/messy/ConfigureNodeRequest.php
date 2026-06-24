<?php

namespace App\NodeConfig;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the inbound "configure a node" editor payload — but hands the raw
 * untyped array straight back out instead of typing it.
 */
final class ConfigureNodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'node_id' => ['required', 'string'],
            'config' => ['array'],
            'config.timeout' => ['integer', 'min:0'],
            'config.retries' => ['integer', 'min:0'],
            'config.label' => ['string'],
        ];
    }
}
