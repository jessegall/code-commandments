<?php

namespace App\NodeConfig;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types the inbound "configure a node" editor payload.
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

    public function nodeId(): string
    {
        return $this->string('node_id');
    }

    public function rawConfig(): RawNodeConfig
    {
        return new RawNodeConfig($this->array('config'));
    }
}
