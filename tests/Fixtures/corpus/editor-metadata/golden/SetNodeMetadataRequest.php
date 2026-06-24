<?php

namespace App\EditorMetadata;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types the inbound "set node branches" editor action.
 */
final class SetNodeMetadataRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'node_id' => ['required', 'string'],
            'branches' => ['required', 'array'],
        ];
    }

    public function nodeId(): string
    {
        return $this->string('node_id');
    }

    /**
     * The loose branch shorthands, each a label string or an attribute array.
     *
     * @return list<string|array<string, mixed>>
     */
    public function branches(): array
    {
        return collect($this->array('branches'))->values()->all();
    }
}
