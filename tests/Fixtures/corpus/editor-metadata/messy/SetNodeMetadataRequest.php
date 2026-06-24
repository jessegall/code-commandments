<?php

namespace App\EditorMetadata;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the inbound editor action.
 *
 * SYMPTOM: the boundary that SHOULD have typed `value` instead validates it as a
 * bare `present` and hands back a `mixed`. `patch()` returns an array of mixed,
 * pushing the typing decision downstream onto every consumer of the payload.
 */
class SetNodeMetadataRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'node_id' => ['required', 'string'],
            'aspect' => ['required', 'string'],
            // value is whatever — could be a string, an array, or null
            'value' => ['present'],
        ];
    }

    /**
     * The raw patch the editor sent. Returns an array of mixed because the
     * `value` slot is deliberately untyped here.
     *
     * @return array<string, mixed>
     */
    public function patch(): array
    {
        return [
            'node_id' => $this->input('node_id'),
            'aspect' => $this->input('aspect'),
            'value' => $this->input('value'),
        ];
    }
}
